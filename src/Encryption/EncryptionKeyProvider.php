<?php

declare(strict_types=1);

namespace Ubermuda\DoctrineExtra\Encryption;

/**
 * Holds the ordered libsodium secret-box keys: the first key encrypts, every
 * key decrypts. Blank keys after the first are dropped, so an unset previous
 * key means "no previous key".
 *
 * Validation is lazy (in keys(), not the constructor): the encrypted type ships
 * dormant — boot and DB connections must not break when no encrypted column is
 * in use and the key is empty.
 */
final readonly class EncryptionKeyProvider
{
    /** @var list<string> */
    private array $base64Keys;

    /**
     * @param list<string> $base64Keys
     */
    public function __construct(array $base64Keys)
    {
        $first = $base64Keys[0] ?? '';
        $rest = array_filter(\array_slice($base64Keys, 1), static fn (string $key): bool => '' !== trim($key));

        $this->base64Keys = [$first, ...array_values($rest)];
    }

    /**
     * The key that encrypts new values.
     */
    public function key(): string
    {
        return $this->keys()[0];
    }

    /**
     * @return non-empty-list<string> the decoded keys, current key first
     */
    public function keys(): array
    {
        return array_map(static function (string $base64Key): string {
            $decoded = base64_decode($base64Key, true);
            if (false === $decoded || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($decoded)) {
                throw new \RuntimeException('Encryption key must be base64 of 32 random bytes.');
            }

            return $decoded;
        }, $this->base64Keys);
    }
}
