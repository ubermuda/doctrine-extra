<?php

declare(strict_types=1);

namespace Ubermuda\DoctrineExtra\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Ubermuda\DoctrineExtra\Encryption\Encryptor;

final class EncryptedStringType extends Type
{
    public const string NAME = 'encrypted_string';

    public function __construct(
        private readonly Encryptor $encryptor,
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

        return $this->encryptor->encrypt((string) $value);
    }

    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return $this->encryptor->decrypt((string) $value)->plain;
    }
}
