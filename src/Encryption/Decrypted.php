<?php

declare(strict_types=1);

namespace Ubermuda\DoctrineExtra\Encryption;

final readonly class Decrypted
{
    /**
     * @param bool $current true when the current (first) key opened the value
     */
    public function __construct(
        public string $plain,
        public bool $current,
    ) {
    }
}
