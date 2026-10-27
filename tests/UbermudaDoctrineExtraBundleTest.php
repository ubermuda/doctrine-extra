<?php

declare(strict_types=1);

namespace Ubermuda\DoctrineExtra\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Ubermuda\DoctrineExtra\UbermudaDoctrineExtraBundle;

final class UbermudaDoctrineExtraBundleTest extends TestCase
{
    public function test_defaults_to_the_one_env_key(): void
    {
        $this->assertSame(['%env(APP_ENCRYPTION_KEY)%'], $this->keys([]));
    }

    public function test_takes_a_key_list(): void
    {
        $keys = ['%env(APP_ENCRYPTION_KEY)%', '%env(APP_ENCRYPTION_KEY_PREVIOUS)%'];

        $this->assertSame($keys, $this->keys(['encryption_keys' => $keys]));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function keys(array $config): mixed
    {
        $builder = new ContainerBuilder();
        new UbermudaDoctrineExtraBundle()->getContainerExtension()?->load([$config], $builder);

        return $builder->getParameter('ubermuda_doctrine_extra.encryption_keys');
    }
}
