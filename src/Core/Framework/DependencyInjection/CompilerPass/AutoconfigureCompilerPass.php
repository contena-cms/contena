<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use League\Flysystem\FilesystemOperator;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Contena\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Contena\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Contena\Core\Framework\Routing\AbstractRouteScope;
use Contena\Core\Framework\Telemetry\Metrics\Metric\PeriodicMetricCollectorInterface;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\AbstractValueGenerator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AutoconfigureCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container
            ->registerAttributeForAutoconfiguration(Entity::class, static function (ChildDefinition $definition): void {
                $definition->addTag('contena.entity');
            });

        $container
            ->registerForAutoconfiguration(EntityDefinition::class)
            ->addTag('contena.entity.definition');

        $container
            ->registerForAutoconfiguration(AbstractRouteScope::class)
            ->addTag('contena.route_scope');

        $container
            ->registerForAutoconfiguration(EntityExtension::class)
            ->addTag('contena.entity.extension');

        $container
            ->registerForAutoconfiguration(BulkEntityExtension::class)
            ->addTag('contena.bulk.entity.extension');

        $container
            ->registerForAutoconfiguration(ScheduledTask::class)
            ->addTag('contena.scheduled.task');

        $container
            ->registerForAutoconfiguration(PeriodicMetricCollectorInterface::class)
            ->addTag('contena.telemetry.periodic_metric_collector');

        $container
            ->registerForAutoconfiguration(EntityIndexer::class)
            ->addTag('contena.entity_indexer');

        $container
            ->registerForAutoconfiguration(ExceptionHandlerInterface::class)
            ->addTag('contena.dal.exception_handler');

        $container
            ->registerForAutoconfiguration(FieldSerializerInterface::class)
            ->addTag('contena.field_serializer');

        $container
            ->registerForAutoconfiguration(AbstractUrlProvider::class)
            ->addTag('contena.sitemap_url_provider');

        $container
            ->registerForAutoconfiguration(AdapterFactoryInterface::class)
            ->addTag('contena.filesystem.factory');

        $container
            ->registerForAutoconfiguration(AbstractValueGenerator::class)
            ->addTag('contena.value_generator_pattern');

        $container
            ->registerForAutoconfiguration(SeoUrlRouteInterface::class)
            ->addTag('contena.seo_url.route');

        $container
            ->registerForAutoconfiguration(TemplateNamespaceHierarchyBuilderInterface::class)
            ->addTag('contena.twig.hierarchy_builder');

        $container->registerAliasForArgument('contena.filesystem.private', FilesystemOperator::class, 'privateFilesystem');
        $container->registerAliasForArgument('contena.filesystem.public', FilesystemOperator::class, 'publicFilesystem');
    }
}
