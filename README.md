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

To read the key from a different source, override the bundle config:

```yaml
# config/packages/ubermuda_doctrine_extra.yaml
ubermuda_doctrine_extra:
    encryption_key: '%env(MY_OTHER_KEY)%'
```

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
