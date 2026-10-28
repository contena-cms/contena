<?php declare(strict_types=1);

use Contena\Core\Framework\Example\CoreContract;
use Contena\Storefront\Framework\Example\StorefrontImplementation;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(CoreContract::class, StorefrontImplementation::class);

};
