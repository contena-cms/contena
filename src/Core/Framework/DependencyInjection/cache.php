<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollection;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Cache\Http\CacheHeadersService;
use Contena\Core\Framework\Adapter\Cache\Http\CachePolicyProvider;
use Contena\Core\Framework\Adapter\Cache\Http\CachePolicyProviderFactory;
use Contena\Core\Framework\Adapter\Cache\Http\CacheRelevantRulesResolver;
use Contena\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber;
use Contena\Core\Framework\Adapter\Cache\Http\CacheStore;
use Contena\Core\Framework\Adapter\Cache\InvalidateCacheTask;
use Contena\Core\Framework\Adapter\Cache\InvalidateCacheTaskHandler;
use Contena\Core\Framework\Adapter\Cache\InvalidatorStorage\AbstractInvalidatorStorage;
use Contena\Core\Framework\Adapter\Cache\InvalidatorStorage\MySQLInvalidatorStorage;
use Contena\Core\Framework\Adapter\Cache\InvalidatorStorage\RedisInvalidatorStorage;
use Contena\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFoldersHandler;
use Contena\Core\Framework\Adapter\Cache\Message\RefreshHttpCacheMessageHandler;
use Contena\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Contena\Core\Framework\Adapter\Cache\ReverseProxy\FastlyReverseProxyGateway;
use Contena\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyCache;
use Contena\Core\Framework\Adapter\Cache\ReverseProxy\VarnishReverseProxyGateway;
use Contena\Core\Framework\Adapter\Cache\StampedeProtectionConfigurator;
use Contena\Core\Framework\Adapter\Cache\Telemetry\CacheTelemetrySubscriber;
use Contena\Core\Framework\Adapter\Command\CacheClearAllCommand;
use Contena\Core\Framework\Adapter\Command\CacheClearHttpCommand;
use Contena\Core\Framework\Adapter\Command\CacheInvalidateDelayedCommand;
use Contena\Core\Framework\Adapter\Kernel\EsiDecoration;
use Contena\Core\Framework\Adapter\Redis\RedisConnectionProvider;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Contena\Core\Framework\Routing\MaintenanceModeResolver;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\Framework\Util\Backtrace\BacktraceCollector;
use Contena\Core\System\SystemConfig\Event\SystemConfigMultipleChangedEvent;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(StampedeProtectionConfigurator::class)
        ->public()
        ->args([
            param('contena.cache.disable_stampede_protection'),
        ]);

    $services->set('contena.cache.invalidator.storage.redis_adapter', \Redis::class)
        ->public()
        ->factory([service(RedisConnectionProvider::class), 'getConnection'])
        ->args([
            param('contena.cache.invalidation.delay_options.connection'),
        ]);

    $services->set('contena.cache.invalidator.storage.redis', RedisInvalidatorStorage::class)
        ->lazy()
        ->args([
            service('contena.cache.invalidator.storage.redis_adapter'),
            service('logger'),
        ])
        ->tag('contena.cache.invalidator.storage', ['storage' => 'redis']);

    $services->set('contena.cache.invalidator.storage.mysql', MySQLInvalidatorStorage::class)
        ->lazy()
        ->args([
            service(Connection::class),
            service('logger'),
        ])
        ->tag('contena.cache.invalidator.storage', ['storage' => 'mysql']);

    $services->set('contena.cache.invalidator.storage.locator', TaggedServiceLocator::class)
        ->args([
            tagged_locator('contena.cache.invalidator.storage', 'storage'),
        ]);

    $services->set(AbstractInvalidatorStorage::class)
        ->factory([service('contena.cache.invalidator.storage.locator'), 'get'])
        ->args([
            param('contena.cache.invalidation.delay_options.storage'),
        ]);

    $services->set(CacheInvalidator::class)
        ->public()
        ->lazy()
        ->args([
            [
                service('cache.object'),
                service('cache.http'),
            ],
            service(AbstractInvalidatorStorage::class),
            service('event_dispatcher'),
            service(LoggerInterface::class),
            service('request_stack'),
            service('cache.http'),
            param('contena.http_cache.soft_purge'),
            param('contena.cache.invalidation.delay_enabled'),
            param('contena.cache.invalidation.tag_invalidation_log_enabled'),
            service(BacktraceCollector::class),
            service(ClockInterface::class),
            service(AbstractReverseProxyGateway::class)->nullOnInvalid(),
        ]);

    $services->set(InvalidateCacheTask::class)
        ->tag('contena.scheduled.task');

    $services->set(InvalidateCacheTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(CacheInvalidator::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(CacheClearer::class)
        ->args([
            [
                'object' => service('cache.object'),
                'http' => service('cache.http'),
            ],
            service('cache_clearer'),
            service(AbstractReverseProxyGateway::class)->nullOnInvalid(),
            service(CacheInvalidator::class),
            service('filesystem'),
            param('kernel.cache_dir'),
            param('kernel.environment'),
            param('contena.deployment.cluster_setup'),
            param('contena.http_cache.reverse_proxy.enabled'),
            service('messenger.default_bus'),
            service('logger'),
            service('lock.factory'),
        ]);

    $services->set(CleanupOldCacheFoldersHandler::class)
        ->args([
            service(CacheClearer::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(RefreshHttpCacheMessageHandler::class)
        ->args([
            service('http_kernel.cache.inner'),
            service(CacheStore::class),
            service('cache.http'),
        ])
        ->tag('messenger.message_handler');

    $services->set(CacheInvalidationSubscriber::class)
        ->args([
            service(CacheInvalidator::class),
        ])
        ->tag('kernel.event_listener', ['event' => PluginPostInstallEvent::class, 'method' => 'invalidateConfig', 'priority' => 2001])
        ->tag('kernel.event_listener', ['event' => PluginPostActivateEvent::class, 'method' => 'invalidateConfig', 'priority' => 2001])
        ->tag('kernel.event_listener', ['event' => PluginPostUpdateEvent::class, 'method' => 'invalidateConfig', 'priority' => 2001])
        ->tag('kernel.event_listener', ['event' => PluginPostDeactivateEvent::class, 'method' => 'invalidateConfig', 'priority' => 2001])
        ->tag('kernel.event_listener', ['event' => SystemConfigMultipleChangedEvent::class, 'method' => 'invalidateConfigKey', 'priority' => 2000]);

    $services->set(CacheTagCollector::class)
        ->args([
            service('request_stack'),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_listener');

    $services->set(CacheTagCollection::class);

    $services->set(CachePolicyProvider::class)
        ->factory([CachePolicyProviderFactory::class, 'create'])
        ->args([
            param('contena.http_cache.policies'),
            param('contena.http_cache.route_policies'),
            param('contena.http_cache.default_policies'),
        ]);

    $services->set(CacheResponseSubscriber::class)
        ->args([
            param('contena.http.cache.enabled'),
            service(MaintenanceModeResolver::class),
            service(CacheHeadersService::class),
            service(CachePolicyProvider::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CacheHeadersService::class)
        ->args([
            service(ExtensionDispatcher::class),
            service(CacheRelevantRulesResolver::class),
            param('contena.http_cache.cookies'),
            service('event_dispatcher'),
        ]);

    $services->set(CacheRelevantRulesResolver::class)
        ->args([
            service(ExtensionDispatcher::class),
        ]);

    $services->set('esi', EsiDecoration::class);

    $services->set(ReverseProxyCache::class)
        ->args([
            service(AbstractReverseProxyGateway::class),
            param('contena.cache.invalidation.http_cache'),
            service(CacheTagCollector::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(CacheInvalidateDelayedCommand::class)
        ->tag('console.command')
        ->args([
            service(CacheInvalidator::class),
        ]);

    $services->set(CacheClearAllCommand::class)
        ->args([
            service(CacheClearer::class),
            param('kernel.environment'),
            param('kernel.debug'),
        ])
        ->tag('console.command');

    $services->set(CacheClearHttpCommand::class)
        ->args([
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set('contena.reverse_proxy.http_client', Client::class);

    $services->set(AbstractReverseProxyGateway::class, VarnishReverseProxyGateway::class)
        ->args([
            param('contena.http_cache.reverse_proxy.hosts'),
            param('contena.http_cache.reverse_proxy.max_parallel_invalidations'),
            service('contena.reverse_proxy.http_client'),
            service('logger'),
        ]);

    $services->set(FastlyReverseProxyGateway::class)
        ->args([
            service('contena.reverse_proxy.http_client'),
            param('contena.http_cache.reverse_proxy.fastly.service_id'),
            param('contena.http_cache.reverse_proxy.fastly.api_key'),
            param('contena.http_cache.reverse_proxy.fastly.soft_purge'),
            param('contena.http_cache.reverse_proxy.max_parallel_invalidations'),
            param('contena.http_cache.reverse_proxy.fastly.tag_prefix'),
            param('contena.http_cache.reverse_proxy.fastly.instance_tag'),
            env('APP_URL'),
            service('logger'),
        ]);

    $services->set(CacheTelemetrySubscriber::class)
        ->args([
            service(Meter::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('contena.telemetry.subscriber');
};
