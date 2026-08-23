<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Contena\Core\Content\LandingPage\Aggregate\LandingPageChannel\LandingPageChannelDefinition;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageSpecificationSource;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageTag\LandingPageTagDefinition;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationDefinition;
use Contena\Core\Content\LandingPage\Channel\ChannelLandingPageDefinition;
use Contena\Core\Content\LandingPage\Channel\LandingPageRoute;
use Contena\Core\Content\LandingPage\DataAbstractionLayer\LandingPageIndexer;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\LandingPage\LandingPageValidator;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Contena\Core\Framework\ContentSystem\Helper\ContentLayoutMetadataDeriver;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->set(LandingPageDefinition::class)->tag('contena.entity.definition');
    $services->set(LandingPageTranslationDefinition::class)->tag('contena.entity.definition');
    $services->set(LandingPageTagDefinition::class)->tag('contena.entity.definition');
    $services->set(LandingPageChannelDefinition::class)->tag('contena.entity.definition');
    $services->set(LandingPageIndexer::class)->args([service(IteratorFactory::class), service('landing_page.repository'), service('event_dispatcher')])->tag('contena.entity_indexer', ['priority' => 1000]);
    $services->set(ChannelLandingPageDefinition::class)->tag('contena.channel.entity.definition');
    $services->set(LandingPageRoute::class)->public()->args([service('channel.landing_page.repository'), service(CacheTagCollector::class)]);
    $services->set(LandingPageValidator::class)->args([service('validator')])->tag('kernel.event_subscriber');
    $services->set(LandingPageContentLayoutDefinition::class)->args([service(ContentLayoutMetadataDeriver::class)])->tag('contena.entity.definition');
    $services->set(LandingPageSpecificationSource::class)->args([service('landing_page_content_layout.repository'), service(LandingPageContentLayoutDefinition::class), service(EntityLayoutContextFactory::class)])->tag('content_system.entity_specification_source', ['priority' => 100]);
};
