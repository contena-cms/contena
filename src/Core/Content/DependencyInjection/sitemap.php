<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Content\Sitemap\Channel\SitemapFileRoute;
use Contena\Core\Content\Sitemap\Channel\SitemapRoute;
use Contena\Core\Content\Sitemap\Commands\SitemapGenerateCommand;
use Contena\Core\Content\Sitemap\ConfigHandler\File;
use Contena\Core\Content\Sitemap\Provider\BlogUrlProvider;
use Contena\Core\Content\Sitemap\Provider\CategoryUrlProvider;
use Contena\Core\Content\Sitemap\Provider\CustomUrlProvider;
use Contena\Core\Content\Sitemap\Provider\HomeUrlProvider;
use Contena\Core\Content\Sitemap\Provider\LandingPageUrlProvider;
use Contena\Core\Content\Sitemap\ScheduledTask\SitemapGenerateTask;
use Contena\Core\Content\Sitemap\ScheduledTask\SitemapGenerateTaskHandler;
use Contena\Core\Content\Sitemap\ScheduledTask\SitemapMessageHandler;
use Contena\Core\Content\Sitemap\Service\ConfigHandler;
use Contena\Core\Content\Sitemap\Service\SitemapChannelProvider;
use Contena\Core\Content\Sitemap\Service\SitemapExporter;
use Contena\Core\Content\Sitemap\Service\SitemapHandleFactory;
use Contena\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface;
use Contena\Core\Content\Sitemap\Service\SitemapLister;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SitemapExporter::class)
        ->args([
            tagged_iterator('contena.sitemap_url_provider'),
            service('cache.system'),
            param('contena.sitemap.batchsize'),
            service('contena.filesystem.sitemap'),
            service(SitemapHandleFactoryInterface::class),
            service('event_dispatcher'),
        ]);

    $services->set(SitemapLister::class)
        ->args([
            service('contena.filesystem.sitemap'),
            service('contena.asset.sitemap'),
            service(ClockInterface::class),
        ]);

    $services->set(ConfigHandler::class)
        ->args([
            tagged_iterator('contena.sitemap.config_handler'),
        ]);

    $services->set(SitemapHandleFactoryInterface::class, SitemapHandleFactory::class)
        ->args([
            service('event_dispatcher'),
        ]);

    $services->set(SitemapRoute::class)
        ->public()
        ->args([
            service(SitemapLister::class),
            service(SystemConfigService::class),
            service(SitemapExporter::class),
            service(CacheTagCollector::class),
        ]);

    $services->set(SitemapFileRoute::class)
        ->public()
        ->args([
            service('contena.filesystem.sitemap'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(HomeUrlProvider::class)
        ->tag('contena.sitemap_url_provider');

    $services->set(CategoryUrlProvider::class)
        ->args([
            service(ConfigHandler::class),
            service(Connection::class),
            service(CategoryDefinition::class),
            service(IteratorFactory::class),
            service(EntityRouteResolver::class),
            service('event_dispatcher'),
        ])
        ->tag('contena.sitemap_url_provider');

    $services->set(CustomUrlProvider::class)
        ->args([
            service(ConfigHandler::class),
        ])
        ->tag('contena.sitemap_url_provider');

    $services->set(BlogUrlProvider::class)
        ->args([
            service(ConfigHandler::class),
            service(Connection::class),
            service(BlogDefinition::class),
            service(IteratorFactory::class),
            service(EntityRouteResolver::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ])
        ->tag('contena.sitemap_url_provider');

    $services->set(LandingPageUrlProvider::class)
        ->args([
            service(ConfigHandler::class),
            service(Connection::class),
            service(EntityRouteResolver::class),
            service('event_dispatcher'),
        ])
        ->tag('contena.sitemap_url_provider');

    $services->set(File::class)
        ->args([
            param('contena.sitemap'),
        ])
        ->tag('contena.sitemap.config_handler');

    $services->set(SitemapChannelProvider::class)
        ->args([
            service('channel.repository'),
        ]);

    $services->set(SitemapGenerateCommand::class)
        ->args([
            service(SitemapChannelProvider::class),
            service(SitemapExporter::class),
            service(ChannelContextFactory::class),
            service('event_dispatcher'),
        ])
        ->tag('console.command');

    $services->set(SitemapGenerateTask::class)
        ->tag('contena.scheduled.task');

    $services->set(SitemapGenerateTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(SitemapChannelProvider::class),
            service(SystemConfigService::class),
            service('messenger.default_bus'),
            service('event_dispatcher'),
        ])
        ->tag('messenger.message_handler');

    $services->set(SitemapMessageHandler::class)
        ->args([
            service(ChannelContextFactory::class),
            service(SitemapExporter::class),
            service('logger'),
            service(SystemConfigService::class),
        ])
        ->tag('messenger.message_handler');
};
