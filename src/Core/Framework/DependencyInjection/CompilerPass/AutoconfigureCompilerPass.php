<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use League\Flysystem\FilesystemOperator;
use Contena\Core\Checkout\Cart\CartDataCollectorInterface;
use Contena\Core\Checkout\Cart\CartProcessorInterface;
use Contena\Core\Checkout\Cart\CartValidatorInterface;
use Contena\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Contena\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Contena\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Contena\Core\System\User\Password\LegacyEncoder\LegacyEncoderInterface;
use Contena\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Contena\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Contena\Core\Checkout\Promotion\Cart\Discount\Filter\FilterPickerInterface;
use Contena\Core\Checkout\Promotion\Cart\Discount\Filter\FilterSorterInterface;
use Contena\Core\Content\Cms\DataResolver\Element\CmsElementResolverInterface;
use Contena\Core\Content\Flow\Dispatching\Storer\FlowStorer;
use Contena\Core\Content\Blog\Channel\Listing\Filter\AbstractListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\Processor\AbstractListingProcessor;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteLoaderInterface;
use Contena\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Contena\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Contena\Core\Framework\Api\Cors\CorsHeaderProviderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Contena\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Contena\Core\Framework\Routing\AbstractRouteScope;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Telemetry\Metrics\Metric\PeriodicMetricCollectorInterface;
use Contena\Core\Framework\Webhook\Hookable\HookableEntityInterface;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\AbstractValueGenerator;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Tax\TaxRuleType\TaxRuleTypeFilterInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[BecomesInternal(version: 'v6.8.0')]
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
            ->registerForAutoconfiguration(HookableEntityInterface::class)
            ->addTag('contena.entity.hookable');

        $container
            ->registerForAutoconfiguration(ChannelDefinition::class)
            ->addTag('contena.channel.entity.definition');

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
            ->registerForAutoconfiguration(CartProcessorInterface::class)
            ->addTag('contena.cart.processor');

        $container
            ->registerForAutoconfiguration(CartDataCollectorInterface::class)
            ->addTag('contena.cart.collector');

        $container
            ->registerForAutoconfiguration(ScheduledTask::class)
            ->addTag('contena.scheduled.task');

        $container
            ->registerForAutoconfiguration(PeriodicMetricCollectorInterface::class)
            ->addTag('contena.telemetry.periodic_metric_collector');

        $container
            ->registerForAutoconfiguration(CartValidatorInterface::class)
            ->addTag('contena.cart.validator');

        $container
            ->registerForAutoconfiguration(LineItemFactoryInterface::class)
            ->addTag('contena.cart.line_item.factory');

        $container
            ->registerForAutoconfiguration(LineItemGroupPackagerInterface::class)
            ->addTag('lineitem.group.packager');

        $container
            ->registerForAutoconfiguration(LineItemGroupSorterInterface::class)
            ->addTag('lineitem.group.sorter');

        $container
            ->registerForAutoconfiguration(LegacyEncoderInterface::class)
            ->addTag('contena.legacy_encoder');

        $container
            ->registerForAutoconfiguration(EntityIndexer::class)
            ->addTag('contena.entity_indexer');

        $container
            ->registerForAutoconfiguration(ExceptionHandlerInterface::class)
            ->addTag('contena.dal.exception_handler');

        $container
            ->registerForAutoconfiguration(AbstractDocumentRenderer::class)
            ->addTag('document.renderer');

        $container
            ->registerForAutoconfiguration(AbstractPaymentHandler::class)
            ->addTag('contena.payment.method');

        $container
            ->registerForAutoconfiguration(FilterSorterInterface::class)
            ->addTag('promotion.filter.sorter');

        $container
            ->registerForAutoconfiguration(FilterPickerInterface::class)
            ->addTag('promotion.filter.picker');

        $container
            ->registerForAutoconfiguration(Rule::class)
            ->addTag('contena.rule.definition');

        $container
            ->registerForAutoconfiguration(AbstractTaxProvider::class)
            ->addTag('contena.tax.provider');

        $container
            ->registerForAutoconfiguration(CmsElementResolverInterface::class)
            ->addTag('contena.cms.data_resolver');

        $container
            ->registerForAutoconfiguration(FieldSerializerInterface::class)
            ->addTag('contena.field_serializer');

        $container
            ->registerForAutoconfiguration(FlowStorer::class)
            ->addTag('flow.storer');

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
            ->registerForAutoconfiguration(TaxRuleTypeFilterInterface::class)
            ->addTag('tax.rule_type_filter');

        $container
            ->registerForAutoconfiguration(SeoUrlRouteInterface::class)
            ->addTag('contena.seo_url.route');

        $container
            ->registerForAutoconfiguration(SeoUrlRouteLoaderInterface::class)
            ->addTag('contena.seo_url.route_loader');

        $container
            ->registerForAutoconfiguration(TemplateNamespaceHierarchyBuilderInterface::class)
            ->addTag('contena.twig.hierarchy_builder');

        $container
            ->registerForAutoconfiguration(AbstractListingProcessor::class)
            ->addTag('contena.listing.processor');

        $container
            ->registerForAutoconfiguration(AbstractListingFilterHandler::class)
            ->addTag('contena.listing.filter.handler');

        $container
            ->registerForAutoconfiguration(CorsHeaderProviderInterface::class)
            ->addTag(CorsHeaderProviderInterface::SERVICE_TAG);

        $container->registerAliasForArgument('contena.filesystem.private', FilesystemOperator::class, 'privateFilesystem');
        $container->registerAliasForArgument('contena.filesystem.public', FilesystemOperator::class, 'publicFilesystem');
    }
}
