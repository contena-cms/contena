<?php declare(strict_types=1);

namespace Contena\Storefront\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewPageBuilder;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewPayloadStore;
use Contena\Storefront\Controller\ContentSystemPreviewController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->public();

































    $services->set(ContentSystemPreviewController::class)
        ->public()
        ->args([
            service(ContentPreviewPayloadStore::class),
            service(ContentPreviewPageBuilder::class),
            service(Connection::class),
        ])
        ->call('setContainer', [service('service_container')]);
};
