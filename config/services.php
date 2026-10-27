<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Ubermuda\DoctrineExtra\Command\ReencryptConsoleCommand;
use Ubermuda\DoctrineExtra\Encryption\EncryptionKeyProvider;
use Ubermuda\DoctrineExtra\Encryption\EncryptionMiddleware;
use Ubermuda\DoctrineExtra\Encryption\Encryptor;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->set(EncryptionKeyProvider::class)
        ->arg('$base64Keys', param('ubermuda_doctrine_extra.encryption_keys'));

    $services->set(Encryptor::class);

    $services->set(EncryptionMiddleware::class);

    $services->set(ReencryptConsoleCommand::class);
};
