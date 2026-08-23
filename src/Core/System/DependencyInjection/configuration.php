<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\SystemConfig\AbstractSystemConfigLoader;
use Contena\Core\System\SystemConfig\Api\SystemConfigController;
use Contena\Core\System\SystemConfig\CachedSystemConfigLoader;
use Contena\Core\System\SystemConfig\Channel\SiteSettingsRoute;
use Contena\Core\System\SystemConfig\Command\ConfigGet;
use Contena\Core\System\SystemConfig\Command\ConfigSet;
use Contena\Core\System\SystemConfig\ConfiguredSystemConfigLoader;
use Contena\Core\System\SystemConfig\MemoizedSystemConfigLoader;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Contena\Core\System\SystemConfig\Store\MemoizedSystemConfigStore;
use Contena\Core\System\SystemConfig\SymfonySystemConfigService;
use Contena\Core\System\SystemConfig\SystemConfigDefinition;
use Contena\Core\System\SystemConfig\SystemConfigLoader;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\SystemConfig\Util\ConfigReader;
use Contena\Core\System\SystemConfig\Validation\SystemConfigValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\KernelInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SystemConfigValidator::class)
        ->args([
            service(ConfigurationService::class),
            service(DataValidator::class),
        ])
        ->tag('contena.system_config.validation');

    $services->set(SystemConfigDefinition::class)
        ->tag('contena.entity.definition');

    $services->set('kernel.bundles', \Iterator::class)
        ->factory([service('kernel'), 'getBundles']);

    $services->set(ConfigurationService::class)
        ->args([
            service('kernel.bundles'),
            service(ConfigReader::class),
            service(SystemConfigService::class),
            service('logger'),
        ]);

    $services->set(ConfigReader::class)
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(SystemConfigController::class)
        ->public()
        ->args([
            service(ConfigurationService::class),
            service(SystemConfigService::class),
            service(SystemConfigValidator::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(SystemConfigService::class)
        ->public()
        ->lazy()
        ->args([
            service(Connection::class),
            service(ConfigReader::class),
            service(AbstractSystemConfigLoader::class),
            service('event_dispatcher'),
            service(SymfonySystemConfigService::class),
            service(CacheTagCollector::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(SiteSettingsRoute::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(MemoizedSystemConfigStore::class)
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(SymfonySystemConfigService::class)
        ->args([
            param('contena.system_config'),
        ]);

    $services->set(SystemConfigLoader::class)
        ->args([
            service(Connection::class),
            service(KernelInterface::class),
        ]);

    $services->set(ConfiguredSystemConfigLoader::class)
        ->decorate(SystemConfigLoader::class, null, -1500)
        ->args([
            service(ConfiguredSystemConfigLoader::class . '.inner'),
            service(SymfonySystemConfigService::class),
        ]);

    $services->set(CachedSystemConfigLoader::class)
        ->decorate(SystemConfigLoader::class, null, -1000)
        ->args([
            service(CachedSystemConfigLoader::class . '.inner'),
            service('cache.object'),
        ]);

    $services->set(MemoizedSystemConfigLoader::class)
        ->decorate(SystemConfigLoader::class, null, -2000)
        ->args([
            service(MemoizedSystemConfigLoader::class . '.inner'),
            service(MemoizedSystemConfigStore::class),
        ]);

    $services->alias(AbstractSystemConfigLoader::class, SystemConfigLoader::class);

    $services->set(ConfigGet::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('console.command');

    $services->set(ConfigSet::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('console.command');
};
