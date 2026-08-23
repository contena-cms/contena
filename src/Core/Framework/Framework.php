<?php declare(strict_types=1);

namespace Contena\Core\Framework;

use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Cache\CacheCompilerPass;
use Contena\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Contena\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyCompilerPass;
use Contena\Core\Framework\Adapter\Cache\StampedeProtectionConfigurator;
use Contena\Core\Framework\Adapter\Redis\RedisConnectionsCompilerPass;
use Contena\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Contena\Core\Framework\DependencyInjection\CompilerPass\AssetBundleRegistrationCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\AssetRegistrationCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\AttributeEntityCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\AutoconfigureCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\ChannelApiMcpServerBuilderCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\ContentLayoutAssignableCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\ContentRouteCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\ContentSystemDataLoaderCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\ContentSystemElementTypeCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\ContentSystemStyleOptionCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\CreateGeneratorScaffoldingCommandPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\DefaultTransportCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\DisableTwigCacheWarmerCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\FeatureFlagCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\FilesystemConfigMigrationCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\FrameworkMigrationReplacementCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\HttpCacheConfigCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\McpServerBuilderCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\McpToolAnalysisCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\McpToolDiscoveryCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\MessengerMiddlewareCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\OverwriteSessionFactoryCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\RateLimiterCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\RedisPrefixCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\RouteScopeCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\ScheduledTaskExecutorCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\TelemetrySubscriberCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\ThemeAssetVersionStrategyCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\TwigEnvironmentCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\TwigLoaderConfigCompilerPass;
use Contena\Core\Framework\DependencyInjection\FrameworkExtension;
use Contena\Core\Framework\Feature\FeatureFlagRegistry;
use Contena\Core\Framework\Increment\IncrementerGatewayCompilerPass;
use Contena\Core\Framework\MessageQueue\MessageHandlerCompilerPass;
use Contena\Core\Framework\Telemetry\Metrics\MeterProvider;
use Contena\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
class Framework extends Bundle
{
    public function getTemplatePriority(): int
    {
        return -1;
    }

    public function getContainerExtension(): Extension
    {
        return new FrameworkExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        $container->setParameter('locale', Defaults::DEFAULT_LOCALE);

        $phpLoader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));

        $phpLoader->load('services.php');
        $phpLoader->load('acl.php');
        $phpLoader->load('cache.php');
        $phpLoader->load('content-system.php');
        $phpLoader->load('seo.php');
        $phpLoader->load('api.php');
        $phpLoader->load('custom-field.php');
        $phpLoader->load('data-abstraction-layer.php');
        $phpLoader->load('event.php');
        $phpLoader->load('hydrator.php');
        $phpLoader->load('filesystem.php');
        $phpLoader->load('message-queue.php');
        $phpLoader->load('plugin.php');
        $phpLoader->load('scheduled-task.php');
        $phpLoader->load('store.php');
        $phpLoader->load('language.php');
        $phpLoader->load('validation.php');
        $phpLoader->load('rate-limiter.php');
        $phpLoader->load('increment.php');
        $phpLoader->load('flag.php');
        $phpLoader->load('health.php');
        $phpLoader->load('telemetry.php');
        $phpLoader->load('notification.php');
        $phpLoader->load('mcp.php');

        if ($container->getParameter('kernel.environment') === 'test') {
            $phpLoader->load('services_test.php');
            $phpLoader->load('seo_test.php');
        }

        /** Needs to run after @see RegisterAutoconfigureAttributesPass (priority 100) to include all services that are autoconfigured */
        $container->addCompilerPass(new AttributeEntityCompilerPass(new AttributeEntityCompiler()), PassConfig::TYPE_BEFORE_OPTIMIZATION, 99);
        // make sure to remove services behind a feature flag, before some other compiler passes may reference them, therefore the high priority
        $container->addCompilerPass(new FeatureFlagCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->addCompilerPass(new EntityCompilerPass());
        $container->addCompilerPass(new DisableTwigCacheWarmerCompilerPass());
        $container->addCompilerPass(new DefaultTransportCompilerPass());
        $container->addCompilerPass(new MessengerMiddlewareCompilerPass());
        $container->addCompilerPass(new TwigLoaderConfigCompilerPass());
        $container->addCompilerPass(new TwigEnvironmentCompilerPass());
        $container->addCompilerPass(new RouteScopeCompilerPass());
        $container->addCompilerPass(new AssetRegistrationCompilerPass());
        $container->addCompilerPass(new AssetBundleRegistrationCompilerPass());
        $container->addCompilerPass(new FilesystemConfigMigrationCompilerPass());
        $container->addCompilerPass(new ThemeAssetVersionStrategyCompilerPass());
        $container->addCompilerPass(new RateLimiterCompilerPass());
        $container->addCompilerPass(new IncrementerGatewayCompilerPass());
        $container->addCompilerPass(new ReverseProxyCompilerPass());
        $container->addCompilerPass(new CacheCompilerPass());
        $container->addCompilerPass(new OverwriteSessionFactoryCompilerPass());
        $container->addCompilerPass(new RedisPrefixCompilerPass(), PassConfig::TYPE_BEFORE_REMOVING, 0);
        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->addCompilerPass(new McpToolDiscoveryCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 900);
        $container->addCompilerPass(new McpToolAnalysisCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 800);
        $container->addCompilerPass(new McpServerBuilderCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 700);
        $container->addCompilerPass(new ChannelApiMcpServerBuilderCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 600);
        $container->addCompilerPass(new HttpCacheConfigCompilerPass());
        $container->addCompilerPass(new MessageHandlerCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->addCompilerPass(new ContentRouteCompilerPass());
        $container->addCompilerPass(new ContentSystemDataLoaderCompilerPass());
        $container->addCompilerPass(new ContentSystemElementTypeCompilerPass());
        $container->addCompilerPass(new ContentSystemStyleOptionCompilerPass());
        $container->addCompilerPass(new ContentLayoutAssignableCompilerPass());
        $container->addCompilerPass(new ScheduledTaskExecutorCompilerPass());
        $container->addCompilerPass(new CreateGeneratorScaffoldingCommandPass());
        $container->addCompilerPass(new RedisConnectionsCompilerPass());
        $container->addCompilerPass(new TelemetrySubscriberCompilerPass());

        if ($container->getParameter('kernel.environment') === 'test') {
            $container->addCompilerPass(new DisableRateLimiterCompilerPass());
        }

        $container->addCompilerPass(new FrameworkMigrationReplacementCompilerPass());
        parent::build($container);
        $this->buildDefaultConfig($container);
    }

    public function boot(): void
    {
        parent::boot();

        \assert($this->container instanceof ContainerInterface, 'Container is not set yet, please call setContainer() before calling boot(), see `src/Core/Kernel.php:186`.');

        $featureFlagRegistry = $this->container->get(FeatureFlagRegistry::class);
        $featureFlagRegistry->register();

        if ($this->container->getParameter('kernel.environment') !== 'test') {
            // Inject the meter early in the application lifecycle. This is needed to use the meter in special case (static contexts).
            MeterProvider::bindMeter($this->container);
        }

        CacheValueCompressor::$compress = $this->container->getParameter('contena.cache.compress');
        CacheValueCompressor::$compressMethod = $this->container->getParameter('contena.cache.compression_method');
        Feature::$emitDeprecations = $this->container->getParameter('kernel.debug');

        $stampedeProtectionConfigurator = $this->container->get(StampedeProtectionConfigurator::class);
        $stampedeProtectionConfigurator->apply();
    }
}
