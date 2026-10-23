<?php declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->parameters()->set('contena.frontend.redirect_on_single_hit_fields', [
        'blogNumber',
        'ean',
        'manufacturerNumber',
    ]);
};
