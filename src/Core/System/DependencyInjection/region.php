<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Contena\Core\System\Region\Aggregate\RegionTranslation\RegionTranslationDefinition;
use Contena\Core\System\Region\Channel\AbstractRegionRoute;
use Contena\Core\System\Region\Channel\ChannelRegionDefinition;
use Contena\Core\System\Region\Channel\RegionRoute;
use Contena\Core\System\Region\DataAbstractionLayer\RegionIndexer;
use Contena\Core\System\Region\RegionDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(RegionDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(RegionTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelRegionDefinition::class)
        ->tag('contena.channel.entity.definition');

    $services->set(RegionRoute::class)
        ->public()
        ->args([
            service('region.repository'),
            service('event_dispatcher'),
            service(CacheTagCollector::class),
        ]);
    $services->alias(AbstractRegionRoute::class, RegionRoute::class);

    $services->set(RegionIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('region.repository'),
            service(Connection::class),
            service('event_dispatcher'),
            service(ChildCountUpdater::class),
            service(TreeUpdater::class),
        ])
        ->tag('contena.entity_indexer');
};
