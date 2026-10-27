<?php

declare(strict_types=1);

namespace Ubermuda\DoctrineExtra\Tests\Encryption;

use PHPUnit\Framework\TestCase;
use Ubermuda\DoctrineExtra\Encryption\EncryptionKeyProvider;
use Ubermuda\DoctrineExtra\Encryption\Encryptor;

final class EncryptorTest extends TestCase
{
    private const string NEW_KEY = 'bmV3bmV3bmV3bmV3bmV3bmV3bmV3bmV3bmV3bmV3bmU=';
    private const string OLD_KEY = 'b2xkb2xkb2xkb2xkb2xkb2xkb2xkb2xkb2xkb2xkb2w=';

    public function test_encrypts_with_the_first_key(): void
    {
        $stored = new Encryptor(new EncryptionKeyProvider([self::NEW_KEY, self::OLD_KEY]))->encrypt('sk-secret');

        $decrypted = new Encryptor(new EncryptionKeyProvider([self::NEW_KEY]))->decrypt($stored);

        $this->assertSame('sk-secret', $decrypted->plain);
        $this->assertTrue($decrypted->current);
    }

    public function test_decrypts_with_the_second_key(): void
    {
        $stored = new Encryptor(new EncryptionKeyProvider([self::OLD_KEY]))->encrypt('sk-secret');

        $decrypted = new Encryptor(new EncryptionKeyProvider([self::NEW_KEY, self::OLD_KEY]))->decrypt($stored);

        $this->assertSame('sk-secret', $decrypted->plain);
        $this->assertFalse($decrypted->current);
    }

    public function test_ignores_a_blank_second_key(): void
    {
        $encryptor = new Encryptor(new EncryptionKeyProvider([self::NEW_KEY, '', '  ']));

        $this->assertSame('sk-secret', $encryptor->decrypt($encryptor->encrypt('sk-secret'))->plain);
    }

    public function test_fails_when_no_key_opens_the_value(): void
    {
        $stored = new Encryptor(new EncryptionKeyProvider([self::OLD_KEY]))->encrypt('sk-secret');

        $this->expectException(\RuntimeException::class);
        new Encryptor(new EncryptionKeyProvider([self::NEW_KEY]))->decrypt($stored);
    }

    public function test_rejects_an_invalid_previous_key(): void
    {
        $this->expectException(\RuntimeException::class);
        new EncryptionKeyProvider([self::NEW_KEY, 'not-a-key'])->keys();
    }

    public function test_rejects_a_blank_current_key_lazily(): void
    {
        $provider = new EncryptionKeyProvider(['']);

        $this->expectException(\RuntimeException::class);
        $provider->key();
    }
}
