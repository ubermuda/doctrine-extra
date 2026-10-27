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
                ->arrayNode('encryption_keys')
                    ->info('Base64-encoded 32-byte libsodium secret-box keys, or %env()% placeholders. The first key encrypts; every key decrypts; blank keys after the first are ignored.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['%env(APP_ENCRYPTION_KEY)%'])
                ->end()
            ->end();
    }

    /**
     * @param array{encryption_keys: list<string>} $config
     */
    #[\Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('ubermuda_doctrine_extra.encryption_keys', $config['encryption_keys']);

        $container->import(__DIR__.'/../config/services.php');
    }
}
