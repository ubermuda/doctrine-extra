<?php

declare(strict_types=1);

namespace Ubermuda\DoctrineExtra\Tests\Command\Fixtures;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'credential')]
class Credential
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'token_ref', type: 'string')]
        public string $ref,
        #[ORM\Column(type: 'encrypted_string', nullable: true)]
        public ?string $secret = null,
    ) {
    }
}
