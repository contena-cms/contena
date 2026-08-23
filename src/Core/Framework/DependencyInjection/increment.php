<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Increment\ArrayIncrementer;
use Contena\Core\Framework\Increment\Controller\IncrementApiController;
use Contena\Core\Framework\Increment\IncrementGatewayRegistry;
use Contena\Core\Framework\Increment\MySQLIncrementer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set('contena.increment.gateway.registry', IncrementGatewayRegistry::class)
        ->public()
        ->args([
            tagged_iterator('contena.increment.gateway'),
        ]);

    $services->set('contena.increment.gateway.mysql', MySQLIncrementer::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set('contena.increment.gateway.array', ArrayIncrementer::class)
        ->tag('kernel.reset', ['method' => 'resetAll']);

    $services->set(IncrementApiController::class)
        ->public()
        ->args([
            service('contena.increment.gateway.registry'),
        ]);
};
