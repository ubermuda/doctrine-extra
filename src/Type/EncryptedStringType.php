<?php

declare(strict_types=1);

namespace Ubermuda\DoctrineExtra\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Ubermuda\DoctrineExtra\Encryption\EncryptionKeyProvider;

final class EncryptedStringType extends Type
{
    public const string NAME = 'encrypted_string';

    public function __construct(
        private readonly EncryptionKeyProvider $keyProvider,
    ) {
    }

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox((string) $value, $nonce, $this->keyProvider->key());

        return base64_encode($nonce.$cipher);
    }

    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }
        $decoded = base64_decode((string) $value, true);
        if (false === $decoded || \strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Corrupt encrypted value.');
        }
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->keyProvider->key());
        if (false === $plain) {
            throw new \RuntimeException('Failed to decrypt value (wrong key or tampered data).');
        }

        return $plain;
    }
}
