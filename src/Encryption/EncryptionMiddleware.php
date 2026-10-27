<?php

declare(strict_types=1);

namespace Ubermuda\DoctrineExtra\Encryption;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Types\Type;
use Ubermuda\DoctrineExtra\Type\EncryptedStringType;

/**
 * Registers the DI-aware EncryptedStringType. Doctrine types are instantiated
 * by a static registry that cannot use constructor injection, so this
 * middleware wires the encryptor-backed instance in at connection time.
 */
#[AsMiddleware]
final readonly class EncryptionMiddleware implements Middleware
{
    public function __construct(
        private Encryptor $encryptor,
    ) {
    }

    #[\Override]
    public function wrap(Driver $driver): Driver
    {
        $instance = new EncryptedStringType($this->encryptor);
        if (Type::hasType(EncryptedStringType::NAME)) {
            Type::overrideType(EncryptedStringType::NAME, $instance);
        } else {
            Type::addType(EncryptedStringType::NAME, $instance);
        }

        return $driver;
    }
}
