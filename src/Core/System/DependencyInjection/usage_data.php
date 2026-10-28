<?php declare(strict_types=1);

/**
 * @codeCoverageIgnore - DI wiring only
 */

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

















    $services->set('contena.usage_data.gateway.client', HttpClientInterface::class)
        ->factory([HttpClient::class, 'create'])
        ->args([
            [
                'base_uri' => '%contena.usage_data.gateway.base_uri%',
            ],
        ]);

};
