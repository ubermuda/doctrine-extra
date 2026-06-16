<?php

declare(strict_types=1);

namespace Ubermuda\DoctrineExtra\Encryption;

/**
 * Holds the libsodium secret-box key used by EncryptedStringType.
 *
 * Validation is lazy (in key(), not the constructor): the encrypted type ships
 * dormant — boot and DB connections must not break when no encrypted column is
 * in use and the key is empty. The key is only required once an encrypted
 * column is actually read or written.
 */
final readonly class EncryptionKeyProvider
{
    public function __construct(
        private string $base64Key,
    ) {
    }

    public function key(): string
    {
        $decoded = base64_decode($this->base64Key, true);
        if (false === $decoded || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($decoded)) {
            throw new \RuntimeException('Encryption key must be base64 of 32 random bytes.');
        }

        return $decoded;
    }
}
