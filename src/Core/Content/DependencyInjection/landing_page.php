<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Contena\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Contena\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageSalesChannel\LandingPageSalesChannelDefinition;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageTag\LandingPageTagDefinition;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationDefinition;
use Contena\Core\Content\LandingPage\DataAbstractionLayer\LandingPageIndexer;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\LandingPage\LandingPageValidator;
use Contena\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Contena\Core\Content\LandingPage\SalesChannel\SalesChannelLandingPageDefinition;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LandingPageDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(LandingPageTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(LandingPageTagDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(LandingPageSalesChannelDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(LandingPageIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('landing_page.repository'),
            service('event_dispatcher'),
        ])
        ->tag('contena.entity_indexer', ['priority' => 1000]);

    $services->set(SalesChannelLandingPageDefinition::class)
        ->tag('contena.sales_channel.entity.definition');

    $services->set(LandingPageRoute::class)
        ->public()
        ->args([
            service('sales_channel.landing_page.repository'),
            service(SalesChannelCmsPageLoader::class),
            service(EntityCmsSlotConfigInheritanceBuilder::class),
            service(SalesChannelLandingPageDefinition::class),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(LandingPageValidator::class)
        ->args([
            service('validator'),
        ])
        ->tag('kernel.event_subscriber');
};
