<?php

declare(strict_types=1);

namespace Ubermuda\DoctrineExtra\Tests\Type;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;
use Ubermuda\DoctrineExtra\Encryption\EncryptionKeyProvider;
use Ubermuda\DoctrineExtra\Encryption\Encryptor;
use Ubermuda\DoctrineExtra\Type\EncryptedStringType;

final class EncryptedStringTypeTest extends TestCase
{
    private EncryptedStringType $type;
    private PostgreSQLPlatform $platform;

    #[\Override]
    protected function setUp(): void
    {
        $this->type = new EncryptedStringType(
            new Encryptor(new EncryptionKeyProvider([base64_encode(str_repeat('k', 32))])),
        );
        $this->platform = new PostgreSQLPlatform();
    }

    public function test_round_trips(): void
    {
        $db = $this->type->convertToDatabaseValue('sk-secret', $this->platform);
        $this->assertNotNull($db);
        $this->assertNotSame('sk-secret', $db);
        $this->assertNotFalse(base64_decode($db, true));
        $this->assertStringNotContainsString('sk-secret', (string) base64_decode($db, true));
        $this->assertSame('sk-secret', $this->type->convertToPHPValue($db, $this->platform));
    }

    public function test_wrong_key_fails_closed(): void
    {
        $db = $this->type->convertToDatabaseValue('sk-secret', $this->platform);
        $wrongKeyType = new EncryptedStringType(
            new Encryptor(new EncryptionKeyProvider([base64_encode(str_repeat('x', 32))])),
        );
        $this->expectException(\RuntimeException::class);
        $wrongKeyType->convertToPHPValue($db, $this->platform);
    }
}
