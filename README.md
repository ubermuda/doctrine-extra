# ubermuda/doctrine-extra

Extra Doctrine types for Symfony projects.

## `encrypted_string`

A Doctrine DBAL column type that transparently encrypts and decrypts string
fields at the persistence boundary using libsodium secret-box
(XSalsa20-Poly1305). Values are stored as `base64(nonce + ciphertext)` and
decrypted on read.

### Installation

```bash
composer require ubermuda/doctrine-extra
```

Register the bundle (Symfony Flex does this automatically):

```php
// config/bundles.php
return [
    // ...
    Ubermuda\DoctrineExtra\UbermudaDoctrineExtraBundle::class => ['all' => true],
];
```

### Configuration

The encryption key is read from the `APP_ENCRYPTION_KEY` environment variable by
default — a base64-encoded 32-byte key:

```bash
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

```dotenv
# .env.local
APP_ENCRYPTION_KEY=base64-of-32-random-bytes
```

To read the key from a different source, or to rotate it, set a key list. The
first key encrypts new values. Every key decrypts. A blank key after the first is
ignored, so an unset previous key means "no previous key":

```yaml
# config/packages/ubermuda_doctrine_extra.yaml
ubermuda_doctrine_extra:
    encryption_keys:
        - '%env(APP_ENCRYPTION_KEY)%'
        - '%env(APP_ENCRYPTION_KEY_PREVIOUS)%'
```

### Rotating the key

1. Put the new key first and the old key second, and deploy.
2. Run `bin/console doctrine-extra:reencrypt`. It finds every `encrypted_string`
   column in the ORM metadata and rewrites each value that the old key opened
   under the new key, in batches (`--batch-size`, default 100). It prints counts
   per column and never a value. It is safe to run again after a failure.
3. When the command reports no unreadable values, remove the old key and deploy.

A value that no key opens is listed by table and identifier, left as it is, and
makes the command exit with a failure code.

### Usage

Map any string property as `encrypted_string`:

```php
#[ORM\Column(type: 'encrypted_string')]
public ?string $apiToken = null;
```

The key is validated lazily — boot and database connections are unaffected when
no encrypted column is read or written, so the type can ship dormant.

## License

MIT
