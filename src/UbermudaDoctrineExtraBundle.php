<?php

declare(strict_types=1);

namespace Ubermuda\DoctrineExtra;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class UbermudaDoctrineExtraBundle extends AbstractBundle
{
    #[\Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('encryption_key')
                    ->info('Base64-encoded 32-byte libsodium secret-box key, or an %env()% placeholder.')
                    ->defaultValue('%env(APP_ENCRYPTION_KEY)%')
                ->end()
            ->end();
    }

    /**
     * @param array{encryption_key: string} $config
     */
    #[\Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('ubermuda_doctrine_extra.encryption_key', $config['encryption_key']);

        $container->import(__DIR__.'/../config/services.php');
    }
}
