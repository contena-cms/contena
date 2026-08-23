<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Contena\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Contena\Core\Framework\Feature\FeatureFlagRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(FeatureFlagRegistry::class)
        ->public()
        ->args([
            service(AbstractKeyValueStorage::class),
            service('event_dispatcher'),
            param('contena.feature.flags'),
            param('contena.feature_toggle.enable'),
        ]);
};
