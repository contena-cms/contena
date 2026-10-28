<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\MeasurementSystem\MeasurementUnits;
use Contena\Core\Content\MeasurementSystem\MeasurementUnitTypeEnum;
use Contena\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementEnum;
use Contena\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementUnitBuilder;
use Contena\Core\Content\MeasurementSystem\Unit\AbstractMeasurementUnitConverter;
use Contena\Core\Content\Product\AbstractIsNewDetector;
use Contena\Core\Content\Product\AbstractProductMaxPurchaseCalculator;
use Contena\Core\Content\Product\AbstractProductVariationBuilder;
use Contena\Core\Content\Product\AbstractPropertyGroupSorter;
use Contena\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Content\Product\ProductEvents;
use Contena\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('inventory')]
class ProductSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractProductVariationBuilder $productVariationBuilder,
        private readonly AbstractProductPriceCalculator $calculator,
        private readonly AbstractPropertyGroupSorter $propertyGroupSorter,
        private readonly AbstractProductMaxPurchaseCalculator $maxPurchaseCalculator,
        private readonly AbstractIsNewDetector $isNewDetector,
        private readonly SystemConfigService $systemConfigService,
        private readonly ProductMeasurementUnitBuilder $measurementUnitBuilder,
        private readonly AbstractMeasurementUnitConverter $measurementUnitConverter,
        private readonly RequestStack $requestStack,
        private readonly Connection $connection
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_LOADED_EVENT => 'loaded',
            'product.partial_loaded' => 'loaded',
            'sales_channel.' . ProductEvents::PRODUCT_LOADED_EVENT => 'salesChannelLoaded',
            'sales_channel.product.partial_loaded' => 'salesChannelLoaded',
            EntityWriteEvent::class => 'beforeWriteProduct',
            EntityDeleteEvent::class => 'beforeDeleteProduct',
        ];
    }

    /**
     * @param EntityLoadedEvent<ProductEntity|PartialEntity> $event
     */
    public function loaded(EntityLoadedEvent $event): void
    {
        $isAdminSource = $event->getContext()->getSource() instanceof AdminApiSource;

        foreach ($event->getEntities() as $product) {
            if (!$product instanceof ProductEntity && !$product instanceof PartialEntity) {
                continue;
            }

            if ($isAdminSource) {
                $this->convertMeasurementUnit($product);
            }

            $this->setDefaultLayout($product);

            $this->productVariationBuilder->build($product);
        }
    }

    /**
     * @param SalesChannelEntityLoadedEvent<ProductEntity|PartialEntity> $event
     */
    public function salesChannelLoaded(SalesChannelEntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $product) {
            $price = $product->get('cheapestPrice');

            if ($price instanceof CheapestPriceContainer) {
                $product->assign([
                    'cheapestPrice' => $price->resolve($event->getContext()),
                    'cheapestPriceContainer' => $price,
                ]);
            }

            $assigns = [];

            if (($properties = $product->get('properties')) !== null) {
                $assigns['sortedProperties'] = $this->propertyGroupSorter->sortUsingLocaleCode(
                    $properties,
                    $event->getSalesChannelContext()->getLanguageInfo()->localeCode,
                );
            }

            $assigns['calculatedMaxPurchase'] = $this->maxPurchaseCalculator->calculate($product, $event->getSalesChannelContext());

            $assigns['isNew'] = $this->isNewDetector->isNew($product, $event->getSalesChannelContext());

            $assigns['measurements'] = $this->measurementUnitBuilder->buildFromContext($product, $event->getSalesChannelContext());

            $product->assign($assigns);

            $this->setDefaultLayout($product, $event->getSalesChannelContext()->getSalesChannelId());

            $this->productVariationBuilder->build($product);
        }

        $this->calculator->calculate($event->getEntities(), $event->getSalesChannelContext());
    }

    public function beforeWriteProduct(EntityWriteEvent $event): void
    {
        $lengthUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT);
        $weightUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT);

        if (!$lengthUnitHeader && !$weightUnitHeader) {
            return;
        }

        $commands = $event->getCommandsForEntity(ProductDefinition::ENTITY_NAME);

        foreach ($commands as $command) {
            $payload = $command->getPayload();

            foreach (ProductMeasurementEnum::DIMENSIONS_MAPPING as $dimension => $type) {
                if (!$command->hasField($dimension) || !\is_float($payload[$dimension] ?? null)) {
                    continue;
                }

                $fromUnit = $type === MeasurementUnitTypeEnum::WEIGHT
                    ? $weightUnitHeader
                    : $lengthUnitHeader;

                $toUnit = $type === MeasurementUnitTypeEnum::WEIGHT
                    ? MeasurementUnits::DEFAULT_WEIGHT_UNIT
                    : MeasurementUnits::DEFAULT_LENGTH_UNIT;

                if ($fromUnit) {
                    $command->addPayload($dimension, $this->measurementUnitConverter->convert(
                        $payload[$dimension],
                        $fromUnit,
                        $toUnit,
                    )->value);
                }
            }
        }
    }

    public function beforeDeleteProduct(EntityDeleteEvent $event): void
    {
        $deletedProductIds = $event->getIds(ProductDefinition::ENTITY_NAME);

        if ($deletedProductIds === []) {
            return;
        }

        $deletedIds = [];
        foreach ($deletedProductIds as $id) {
            $deletedIds[] = \is_string($id) ? $id : $id['id'];
        }

        $parentIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT parent_id
             FROM product
             WHERE id IN (:ids) AND parent_id IS NOT NULL',
            ['ids' => Uuid::fromHexToBytesList($deletedIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        if ($parentIds === []) {
            return;
        }

        $versionBytes = Uuid::fromHexToBytes($event->getContext()->getVersionId());

        $event->addSuccess(function () use ($parentIds, $versionBytes): void {
            $this->cleanupConfiguratorSettings($parentIds, $versionBytes);
        });
    }

    /**
     * @param array<string> $parentIds
     */
    private function cleanupConfiguratorSettings(array $parentIds, string $versionBytes): void
    {
        if ($parentIds === []) {
            return;
        }

        // Clean up configurator settings for parents that no longer have variants using those options
        $this->connection->executeStatement(
            'DELETE FROM product_configurator_setting
             WHERE product_configurator_setting.product_id IN (:parentIds)
             AND product_configurator_setting.product_version_id = :versionId
             AND NOT EXISTS (
                 SELECT 1
                 FROM product_option po
                 INNER JOIN product p ON p.id = po.product_id AND p.version_id = po.product_version_id
                 WHERE p.parent_id = product_configurator_setting.product_id
                     AND p.version_id = :versionId
                     AND po.property_group_option_id = product_configurator_setting.property_group_option_id
                     AND po.product_version_id = :versionId
             )',
            [
                'parentIds' => $parentIds,
                'versionId' => $versionBytes,
            ],
            [
                'parentIds' => ArrayParameterType::BINARY,
            ]
        );
    }

    /**
     * @param Entity $product - typehint as Entity because it could be a ProductEntity or PartialEntity
     */
    private function setDefaultLayout(Entity $product, ?string $salesChannelId = null): void
    {
        if (!$product->has('cmsPageId')) {
            return;
        }

        if ($product->get('cmsPageId') !== null) {
            return;
        }

        $cmsPageId = $this->systemConfigService->get(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $salesChannelId);

        if (!$cmsPageId) {
            return;
        }

        $product->assign(['cmsPageId' => $cmsPageId]);
    }

    private function convertMeasurementUnit(ProductEntity|PartialEntity $product): void
    {
        $lengthUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT);
        $weightUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT);

        if (!$lengthUnitHeader && !$weightUnitHeader) {
            return;
        }

        $toLengthUnit = $lengthUnitHeader ?? MeasurementUnits::DEFAULT_LENGTH_UNIT;
        $toWeightUnit = $weightUnitHeader ?? MeasurementUnits::DEFAULT_WEIGHT_UNIT;

        $converted = $this->measurementUnitBuilder->build($product, $toLengthUnit, $toWeightUnit);

        $assigns = [];

        foreach ($converted->getUnits() as $unit => $convertedUnit) {
            $assigns[$unit] = $convertedUnit->value;
        }

        if ($assigns !== []) {
            $product->assign($assigns);
        }
    }
}
