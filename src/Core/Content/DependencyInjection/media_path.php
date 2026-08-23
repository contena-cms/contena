<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Contena\Core\Content\Media\Core\Application\AbstractMediaUrlGenerator;
use Contena\Core\Content\Media\Core\Application\MediaLocationBuilder;
use Contena\Core\Content\Media\Core\Application\MediaPathStorage;
use Contena\Core\Content\Media\Core\Application\MediaPathUpdater;
use Contena\Core\Content\Media\Core\Application\MediaUrlLoader;
use Contena\Core\Content\Media\Core\Application\RemoteThumbnailLoader;
use Contena\Core\Content\Media\Core\Event\UpdateMediaPathEvent;
use Contena\Core\Content\Media\Core\Event\UpdateThumbnailPathEvent;
use Contena\Core\Content\Media\Core\Strategy\FilenamePathStrategy;
use Contena\Core\Content\Media\Core\Strategy\IdPathStrategy;
use Contena\Core\Content\Media\Core\Strategy\PathStrategyFactory;
use Contena\Core\Content\Media\Core\Strategy\PhysicalFilenamePathStrategy;
use Contena\Core\Content\Media\Core\Strategy\PlainPathStrategy;
use Contena\Core\Content\Media\Event\MediaPathChangedEvent;
use Contena\Core\Content\Media\Infrastructure\Command\UpdatePathCommand;
use Contena\Core\Content\Media\Infrastructure\Path\BanMediaUrl;
use Contena\Core\Content\Media\Infrastructure\Path\FastlyMediaReverseProxy;
use Contena\Core\Content\Media\Infrastructure\Path\MediaPathPostUpdater;
use Contena\Core\Content\Media\Infrastructure\Path\MediaUrlGenerator;
use Contena\Core\Content\Media\Infrastructure\Path\SqlMediaLocationBuilder;
use Contena\Core\Content\Media\Infrastructure\Path\SqlMediaPathStorage;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MediaUrlLoader::class)
        ->args([
            service(AbstractMediaUrlGenerator::class),
            service(RemoteThumbnailLoader::class),
            param('contena.media.remote_thumbnails.enable'),
        ])
        ->tag('kernel.event_listener', ['event' => 'media.loaded', 'method' => 'loaded', 'priority' => 20])
        ->tag('kernel.event_listener', ['event' => 'media.partial_loaded', 'method' => 'loaded', 'priority' => 19]);

    $services->set(RemoteThumbnailLoader::class)
        ->args([
            service(AbstractMediaUrlGenerator::class),
            service(Connection::class),
            service('contena.filesystem.public'),
            service(ExtensionDispatcher::class),
            param('contena.media.remote_thumbnails.pattern'),
            param('contena.media.remote_thumbnails.fallback_sizes'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(MediaLocationBuilder::class, SqlMediaLocationBuilder::class)
        ->args([
            service('event_dispatcher'),
            service(Connection::class),
        ]);

    $services->set(MediaPathUpdater::class)
        ->args([
            service(AbstractMediaPathStrategy::class),
            service(MediaLocationBuilder::class),
            service(MediaPathStorage::class),
        ])
        ->tag('kernel.event_listener', ['event' => UpdateMediaPathEvent::class, 'method' => 'updateMedia', 'priority' => 0])
        ->tag('kernel.event_listener', ['event' => UpdateThumbnailPathEvent::class, 'method' => 'updateThumbnails', 'priority' => 0]);

    $services->set(MediaPathStorage::class, SqlMediaPathStorage::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(PathStrategyFactory::class)
        ->args([
            tagged_iterator('contena.path.strategy'),
        ]);

    $services->set(FilenamePathStrategy::class)
        ->tag('contena.path.strategy');

    $services->set(IdPathStrategy::class)
        ->tag('contena.path.strategy');

    $services->set(PhysicalFilenamePathStrategy::class)
        ->tag('contena.path.strategy');

    $services->set(PlainPathStrategy::class)
        ->tag('contena.path.strategy');

    $services->set(AbstractMediaUrlGenerator::class, MediaUrlGenerator::class)
        ->args([
            service('contena.filesystem.public'),
        ]);

    $services->set(AbstractMediaPathStrategy::class)
        ->factory([service(PathStrategyFactory::class), 'factory'])
        ->args([
            param('contena.cdn.strategy'),
        ]);

    $services->set(MediaPathPostUpdater::class)
        ->args([
            service(IteratorFactory::class),
            service(MediaPathUpdater::class),
            service(Connection::class),
            service(EntityIndexerRegistry::class),
        ])
        ->tag('contena.entity_indexer');

    $services->set(UpdatePathCommand::class)
        ->tag('console.command')
        ->args([
            service(MediaPathUpdater::class),
            service(Connection::class),
        ]);

    $services->set(BanMediaUrl::class)
        ->args([
            service('contena.media.reverse_proxy'),
            service(AbstractMediaUrlGenerator::class),
        ])
        ->tag('kernel.event_listener', ['event' => MediaPathChangedEvent::class, 'method' => 'changed']);

    $services->alias('contena.media.reverse_proxy', FastlyMediaReverseProxy::class);

    $services->set(FastlyMediaReverseProxy::class)
        ->args([
            service('contena.reverse_proxy.http_client'),
            param('contena.cdn.fastly.api_key'),
            param('contena.cdn.fastly.soft_purge'),
            param('contena.cdn.fastly.max_parallel_invalidations'),
            service('logger'),
        ]);
};
