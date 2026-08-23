<?php declare(strict_types=1);

namespace Contena\Frontend\DependencyInjection;

use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Contena\Core\Framework\ContentSystem\Adapter\RenderingSpecificationFactory;
use Contena\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Contena\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Contena\Frontend\ContentSystem\Extension\ChannelDomainExtension;
use Contena\Frontend\ContentSystem\Extension\ChannelExtension;
use Contena\Frontend\ContentSystem\Extension\ContentLayoutExtension;
use Contena\Frontend\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Contena\Frontend\ContentSystem\FooterContentLayout\FooterSpecificationSource;
use Contena\Frontend\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;
use Contena\Frontend\ContentSystem\HeaderContentLayout\HeaderSpecificationSource;
use Contena\Frontend\ContentSystem\Validation\HeaderFooterAssignmentWriteValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Entity Definitions
    $services->set(HeaderContentLayoutDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(FooterContentLayoutDefinition::class)
        ->tag('contena.entity.definition');

    // Entity Extensions
    $services->set(ChannelExtension::class)
        ->tag('contena.entity.extension');

    $services->set(ChannelDomainExtension::class)
        ->tag('contena.entity.extension');

    $services->set(ContentLayoutExtension::class)
        ->tag('contena.entity.extension');

    // Section Resolvers (Header/Footer)
    $services->set('content_system.section_resolver.header', RenderingSpecificationResolver::class)
        ->args([
            [service(HeaderSpecificationSource::class)],
            service(RenderingSpecificationFactory::class),
        ])
        ->tag('content_system.section_resolver', ['section' => 'header']);

    $services->set('content_system.section_resolver.footer', RenderingSpecificationResolver::class)
        ->args([
            [service(FooterSpecificationSource::class)],
            service(RenderingSpecificationFactory::class),
        ])
        ->tag('content_system.section_resolver', ['section' => 'footer']);

    // Domain-Aware Specification Sources
    $services->set(HeaderSpecificationSource::class)
        ->args([
            service(DomainAwareLayoutResolver::class),
            service('header_content_layout.repository'),
        ])
        ->tag('content_system.specification_source', ['section' => 'header']);

    $services->set(FooterSpecificationSource::class)
        ->args([
            service(DomainAwareLayoutResolver::class),
            service('footer_content_layout.repository'),
        ])
        ->tag('content_system.specification_source', ['section' => 'footer']);

    // Header/Footer Binding Gate (§8.2, empty root context)
    $services->set(HeaderFooterAssignmentWriteValidator::class)
        ->args([
            service(LayoutRootSourceReader::class),
        ])
        ->tag('kernel.event_subscriber');
};
