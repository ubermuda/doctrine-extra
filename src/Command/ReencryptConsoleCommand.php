<?php

declare(strict_types=1);

namespace Ubermuda\DoctrineExtra\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Ubermuda\DoctrineExtra\Encryption\Encryptor;
use Ubermuda\DoctrineExtra\Type\EncryptedStringType;

/**
 * Rewrites every encrypted_string value that an older key opened under the
 * current key. Reads and writes through DBAL, because the ORM sees no change
 * in the plain text and would write nothing. Each write compares the old
 * value, so a run beside live traffic or after a crash is safe to repeat.
 */
#[AsCommand('doctrine-extra:reencrypt', 'Re-encrypt every encrypted_string column with the current key')]
final readonly class ReencryptConsoleCommand
{
    public function __construct(
        private ManagerRegistry $registry,
        private Encryptor $encryptor,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Rows per batch, one transaction per batch')]
        int $batchSize = 100,
    ): int {
        if ($batchSize < 1) {
            $io->error('The batch size must be at least 1.');

            return Command::INVALID;
        }

        $rows = [];
        $unreadable = [];
        foreach ($this->columns() as $column) {
            $result = $this->reencrypt($column, $batchSize);
            $rows[] = [$column['table'], $column['column'], $result['reencrypted'], $result['skipped'], \count($result['unreadable'])];
            foreach ($result['unreadable'] as $key) {
                $unreadable[] = [$column['table'], $column['column'], $key];
            }
        }

        $io->table(['Table', 'Column', 'Re-encrypted', 'Skipped', 'Unreadable'], $rows);

        if ([] !== $unreadable) {
            $io->error('No key opens these values. They were left as they are.');
            $io->table(['Table', 'Column', 'Identifier'], $unreadable);

            return Command::FAILURE;
        }

        $io->success('Every encrypted value uses the current key.');

        return Command::SUCCESS;
    }

    /**
     * @return list<array{connection: Connection, table: string, column: string, ids: non-empty-list<string>}>
     */
    private function columns(): array
    {
        $columns = [];
        foreach ($this->registry->getManagers() as $manager) {
            if (!$manager instanceof EntityManagerInterface) {
                continue;
            }
            $connection = $manager->getConnection();
            $platform = $connection->getDatabasePlatform();
            $quotes = $manager->getConfiguration()->getQuoteStrategy();

            foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
                if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
                    continue;
                }
                foreach ($metadata->fieldMappings as $field => $mapping) {
                    if (EncryptedStringType::NAME !== $mapping->type || null !== $mapping->inherited) {
                        continue;
                    }
                    $table = $quotes->getTableName($metadata, $platform);
                    $column = $quotes->getColumnName($field, $metadata, $platform);
                    $columns[spl_object_id($connection).'|'.$table.'|'.$column] = [
                        'connection' => $connection,
                        'table' => $table,
                        'column' => $column,
                        'ids' => $this->identifierColumns($metadata, $manager),
                    ];
                }
            }
        }

        return array_values($columns);
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return non-empty-list<string>
     */
    private function identifierColumns(ClassMetadata $metadata, EntityManagerInterface $manager): array
    {
        $ids = $manager->getConfiguration()->getQuoteStrategy()
            ->getIdentifierColumnNames($metadata, $manager->getConnection()->getDatabasePlatform());
        if ([] === $ids) {
            throw new \LogicException(\sprintf('Entity %s has no identifier.', $metadata->getName()));
        }

        return array_values($ids);
    }

    /**
     * @param array{connection: Connection, table: string, column: string, ids: non-empty-list<string>} $column
     *
     * @return array{reencrypted: int, skipped: int, unreadable: list<string>}
     */
    private function reencrypt(array $column, int $batchSize): array
    {
        ['connection' => $connection, 'table' => $table, 'column' => $col, 'ids' => $ids] = $column;
        $result = ['reencrypted' => 0, 'skipped' => 0, 'unreadable' => []];
        $idList = implode(', ', $ids);
        $aliases = implode(', ', array_map(static fn (string $id, int $i): string => $id.' AS k'.$i, $ids, array_keys($ids)));
        $where = implode(' AND ', array_map(static fn (string $id): string => $id.' = ?', $ids));
        $after = null;

        do {
            $sql = \sprintf('SELECT %s, %s AS stored FROM %s WHERE %2$s IS NOT NULL AND %2$s <> \'\'', $aliases, $col, $table);
            $params = [];
            if (null !== $after) {
                $sql .= \sprintf(' AND (%s) > (%s)', $idList, implode(', ', array_fill(0, \count($ids), '?')));
                $params = $after;
            }
            $sql .= \sprintf(' ORDER BY %s LIMIT %d', $idList, $batchSize);

            $batch = $connection->fetchAllAssociative($sql, $params);
            if ([] === $batch) {
                break;
            }

            $connection->transactional(function (Connection $connection) use ($batch, $table, $col, $ids, $where, &$result): void {
                foreach ($batch as $row) {
                    $key = self::key($row, \count($ids));
                    $stored = (string) $row['stored'];
                    try {
                        $decrypted = $this->encryptor->decrypt($stored);
                    } catch (\RuntimeException) {
                        $result['unreadable'][] = implode(', ', array_map(static fn (mixed $v): string => \is_scalar($v) ? (string) $v : '?', $key));
                        continue;
                    }
                    if ($decrypted->current) {
                        ++$result['skipped'];
                        continue;
                    }

                    $changed = $connection->executeStatement(
                        \sprintf('UPDATE %s SET %s = ? WHERE %s AND %2$s = ?', $table, $col, $where),
                        [$this->encryptor->encrypt($decrypted->plain), ...$key, $stored],
                    );
                    // Zero rows: a live write replaced the value first, and it used the current key.
                    0 === $changed ? ++$result['skipped'] : ++$result['reencrypted'];
                }
            });

            $after = self::key($batch[array_key_last($batch)], \count($ids));
        } while (\count($batch) === $batchSize);

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<mixed>
     */
    private static function key(array $row, int $count): array
    {
        return array_map(static fn (int $i): mixed => $row['k'.$i], range(0, $count - 1));
    }
}
