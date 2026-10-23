<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Contena\Core\Content\Cms\CmsPageDefinition;
use Contena\Core\Content\Cms\DataAbstractionLayer\FieldSerializer\SlotConfigFieldSerializer;
use Contena\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Contena\Core\Content\Cms\DataResolver\Element\FormCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\Element\HtmlCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\Element\TextCmsElementResolver;
use Contena\Core\Content\Cms\Channel\CmsRoute;
use Contena\Core\Content\Cms\Channel\ChannelCmsPageLoader;
use Contena\Core\Content\Cms\Service\CmsFormSlotConfigResolver;
use Contena\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Contena\Core\Content\Cms\Subscriber\CmsPageDefaultChangeSubscriber;
use Contena\Core\Content\Cms\Subscriber\CmsVersionMergeSubscriber;
use Contena\Core\Content\Cms\Subscriber\UnusedMediaSubscriber;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CmsPageDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CmsPageTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CmsSectionDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CmsBlockDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CmsSlotDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CmsSlotTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CmsSlotsDataResolver::class)
        ->public()
        ->args([
            tagged_iterator('contena.cms.data_resolver'),
            ['blog' => service('channel.blog.repository')],
            service(DefinitionInstanceRegistry::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(TextCmsElementResolver::class)
        ->args([
            service(HtmlSanitizer::class),
        ])
        ->tag('contena.cms.data_resolver');

    $services->set(HtmlCmsElementResolver::class)
        ->tag('contena.cms.data_resolver');

    $services->set(FormCmsElementResolver::class)
        ->args([
        ])
        ->tag('contena.cms.data_resolver');

    $services->set(SlotConfigFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(ChannelCmsPageLoader::class)
        ->args([
            service('cms_page.repository'),
            service(CmsSlotsDataResolver::class),
            service('event_dispatcher'),
            service(CacheTagCollector::class),
        ]);

    $services->set(CmsFormSlotConfigResolver::class)
        ->args([
            service('category.repository'),
            service('landing_page.repository'),
            service('blog.repository'),
            service('cms_slot.repository'),
            service(SystemConfigService::class),
        ]);

    $services->set(EntityCmsSlotConfigInheritanceBuilder::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CmsRoute::class)
        ->public()
        ->args([
            service(ChannelCmsPageLoader::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CmsPageDefaultChangeSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UnusedMediaSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CmsVersionMergeSubscriber::class)
        ->tag('kernel.event_subscriber');
};
