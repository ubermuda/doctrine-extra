<?php

declare(strict_types=1);

namespace Ubermuda\DoctrineExtra\Encryption;

/**
 * Secret-box encryption over the key list: encrypts with the current key and
 * decrypts with whichever key opens the value. Stored as base64(nonce . ciphertext).
 */
final readonly class Encryptor
{
    public function __construct(
        private EncryptionKeyProvider $keyProvider,
    ) {
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, $this->keyProvider->key()));
    }

    /**
     * @throws \RuntimeException when the value is corrupt or no key opens it
     */
    public function decrypt(string $stored): Decrypted
    {
        $decoded = base64_decode($stored, true);
        if (false === $decoded || \strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Corrupt encrypted value.');
        }
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        foreach ($this->keyProvider->keys() as $index => $key) {
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
            if (false !== $plain) {
                return new Decrypted($plain, 0 === $index);
            }
        }

        throw new \RuntimeException('Failed to decrypt value (wrong key or tampered data).');
    }
}
