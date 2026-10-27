<?php

declare(strict_types=1);

namespace Ubermuda\DoctrineExtra\Tests\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Ubermuda\DoctrineExtra\Command\ReencryptConsoleCommand;
use Ubermuda\DoctrineExtra\Encryption\EncryptionKeyProvider;
use Ubermuda\DoctrineExtra\Encryption\Encryptor;
use Ubermuda\DoctrineExtra\Type\EncryptedStringType;

final class ReencryptConsoleCommandTest extends TestCase
{
    private const string NEW_KEY = 'bmV3bmV3bmV3bmV3bmV3bmV3bmV3bmV3bmV3bmV3bmU=';
    private const string OLD_KEY = 'b2xkb2xkb2xkb2xkb2xkb2xkb2xkb2xkb2xkb2xkb2w=';
    private const string OTHER_KEY = 'b3RoZXJvdGhlcm90aGVyb3RoZXJvdGhlcm90aGVyb3Q=';

    private Connection $connection;
    private ManagerRegistry $registry;
    private Encryptor $rotating;
    private Encryptor $old;
    private Encryptor $current;

    #[\Override]
    protected function setUp(): void
    {
        $this->rotating = new Encryptor(new EncryptionKeyProvider([self::NEW_KEY, self::OLD_KEY]));
        $this->old = new Encryptor(new EncryptionKeyProvider([self::OLD_KEY]));
        $this->current = new Encryptor(new EncryptionKeyProvider([self::NEW_KEY]));

        $type = new EncryptedStringType($this->rotating);
        if (Type::hasType(EncryptedStringType::NAME)) {
            Type::overrideType(EncryptedStringType::NAME, $type);
        } else {
            Type::addType(EncryptedStringType::NAME, $type);
        }

        $config = ORMSetup::createAttributeMetadataConfig([__DIR__.'/Fixtures'], true);
        $config->enableNativeLazyObjects(true);
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $manager = new EntityManager($this->connection, $config);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagers')->willReturn(['default' => $manager]);
        $this->registry = $registry;
    }

    public function test_moves_every_old_key_value_to_the_current_key(): void
    {
        $this->insertCredential('a', $this->old->encrypt('token-a'));
        $this->insertCredential('b', $this->current->encrypt('token-b'));
        $this->insertCredential('c', null);
        $this->insertTenant('acme', 1, $this->old->encrypt('api-1'), $this->old->encrypt('hook-1'));
        $this->insertTenant('acme', 2, $this->old->encrypt('api-2'), null);
        $this->insertTenant('beta', 1, $this->current->encrypt('api-3'), $this->old->encrypt('hook-3'));

        $tester = $this->reencrypt(['--batch-size' => '1']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(
            ['token-a', 'token-b', 'api-1', 'hook-1', 'api-2', 'api-3', 'hook-3'],
            $this->currentPlainValues(),
        );
        $this->assertStringNotContainsString('token-a', $tester->getDisplay());
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $this->insertCredential('a', $this->old->encrypt('token-a'));
        $this->reencrypt([]);
        $stored = $this->connection->fetchOne('SELECT secret FROM credential');

        $tester = $this->reencrypt([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame($stored, $this->connection->fetchOne('SELECT secret FROM credential'));
        $this->assertMatchesRegularExpression('/credential\s+secret\s+0\s+1\s+0/', $tester->getDisplay());
    }

    public function test_a_value_no_key_opens_fails_the_run_and_stays(): void
    {
        $unreadable = new Encryptor(new EncryptionKeyProvider([self::OTHER_KEY]))->encrypt('lost');
        $this->insertCredential('lost-ref', $unreadable);
        $this->insertCredential('ok', $this->old->encrypt('token-ok'));

        $tester = $this->reencrypt([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('lost-ref', $tester->getDisplay());
        $this->assertStringNotContainsString('token-ok', $tester->getDisplay());
        $this->assertSame($unreadable, $this->connection->fetchOne("SELECT secret FROM credential WHERE token_ref = 'lost-ref'"));
        $ok = $this->connection->fetchOne("SELECT secret FROM credential WHERE token_ref = 'ok'");
        $this->assertTrue($this->current->decrypt((string) $ok)->current);
    }

    /**
     * Decrypts every stored value with the current key alone, which throws on any value still under the old key.
     *
     * @return list<string>
     */
    private function currentPlainValues(): array
    {
        $stored = [
            ...$this->connection->fetchFirstColumn('SELECT secret FROM credential WHERE secret IS NOT NULL ORDER BY token_ref'),
            ...array_merge(...array_map(
                array_values(...),
                $this->connection->fetchAllAssociative('SELECT api_token, webhook_secret FROM tenant_secret ORDER BY tenant, slot'),
            )),
        ];

        return array_values(array_map(
            fn (mixed $value): string => $this->current->decrypt((string) $value)->plain,
            array_filter($stored, static fn (mixed $value): bool => null !== $value),
        ));
    }

    /**
     * @param array<string, string> $input
     */
    private function reencrypt(array $input): CommandTester
    {
        $application = new Application();
        $application->addCommand(new ReencryptConsoleCommand($this->registry, $this->rotating));
        $tester = new CommandTester($application->find('doctrine-extra:reencrypt'));
        $tester->execute($input);

        return $tester;
    }

    private function insertCredential(string $ref, ?string $stored): void
    {
        $this->connection->insert('credential', ['token_ref' => $ref, 'secret' => $stored]);
    }

    private function insertTenant(string $tenant, int $slot, ?string $token, ?string $webhook): void
    {
        $this->connection->insert('tenant_secret', ['tenant' => $tenant, 'slot' => $slot, 'api_token' => $token, 'webhook_secret' => $webhook]);
    }
}
