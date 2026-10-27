<?php

declare(strict_types=1);

namespace Ubermuda\DoctrineExtra\Tests\Command\Fixtures;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tenant_secret')]
class TenantSecret
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string')]
        public string $tenant,
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        public int $slot,
        #[ORM\Column(name: 'api_token', type: 'encrypted_string', nullable: true)]
        public ?string $token = null,
        #[ORM\Column(name: 'webhook_secret', type: 'encrypted_string', nullable: true)]
        public ?string $webhook = null,
    ) {
    }
}
