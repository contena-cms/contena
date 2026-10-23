<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Blog\AbstractIsNewDetector;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\BlogEvents;
use Contena\Core\Content\Blog\Channel\Price\AbstractBlogPriceCalculator;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Entity\ChannelEntityLoadedEvent;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
class BlogSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractBlogVariationBuilder $blogVariationBuilder,
        private readonly AbstractBlogPriceCalculator $calculator,
        private readonly AbstractPropertyGroupSorter $propertyGroupSorter,
        private readonly AbstractBlogMaxPurchaseCalculator $maxPurchaseCalculator,
        private readonly AbstractIsNewDetector $isNewDetector,
        private readonly SystemConfigService $systemConfigService,
        private readonly BlogMeasurementUnitBuilder $measurementUnitBuilder,
        private readonly AbstractMeasurementUnitConverter $measurementUnitConverter,
        private readonly RequestStack $requestStack,
        private readonly Connection $connection
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BlogEvents::BLOG_LOADED_EVENT => 'loaded',
            'blog.partial_loaded' => 'loaded',
            'channel.' . BlogEvents::BLOG_LOADED_EVENT => 'channelLoaded',
            'channel.blog.partial_loaded' => 'channelLoaded',
            EntityWriteEvent::class => 'beforeWriteBlog',
            EntityDeleteEvent::class => 'beforeDeleteBlog',
        ];
    }

    /**
     * @param EntityLoadedEvent<BlogEntity|PartialEntity> $event
     */
    public function loaded(EntityLoadedEvent $event): void
    {
        $isAdminSource = $event->getContext()->getSource() instanceof AdminApiSource;

        foreach ($event->getEntities() as $blog) {
            if (!$blog instanceof BlogEntity && !$blog instanceof PartialEntity) {
                continue;
            }

            if ($isAdminSource) {
                $this->convertMeasurementUnit($blog);
            }

            $this->setDefaultLayout($blog);

            $this->blogVariationBuilder->build($blog);
        }
    }

    /**
     * @param ChannelEntityLoadedEvent<BlogEntity|PartialEntity> $event
     */
    public function channelLoaded(ChannelEntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $blog) {
            $price = $blog->get('cheapestPrice');

            if ($price instanceof CheapestPriceContainer) {
                $blog->assign([
                    'cheapestPrice' => $price->resolve($event->getContext()),
                    'cheapestPriceContainer' => $price,
                ]);
            }

            $assigns = [];

            if (($properties = $blog->get('properties')) !== null) {
                $assigns['sortedProperties'] = $this->propertyGroupSorter->sortUsingLocaleCode(
                    $properties,
                    $event->getChannelContext()->getLanguageInfo()->localeCode,
                );
            }

            $assigns['calculatedMaxPurchase'] = $this->maxPurchaseCalculator->calculate($blog, $event->getChannelContext());

            $assigns['isNew'] = $this->isNewDetector->isNew($blog, $event->getChannelContext());

            $assigns['measurements'] = $this->measurementUnitBuilder->buildFromContext($blog, $event->getChannelContext());

            $blog->assign($assigns);

            $this->setDefaultLayout($blog, $event->getChannelContext()->getChannelId());

            $this->blogVariationBuilder->build($blog);
        }

        $this->calculator->calculate($event->getEntities(), $event->getChannelContext());
    }

    public function beforeWriteBlog(EntityWriteEvent $event): void
    {
        $lengthUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT);
        $weightUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT);

        if (!$lengthUnitHeader && !$weightUnitHeader) {
            return;
        }

        $commands = $event->getCommandsForEntity(BlogDefinition::ENTITY_NAME);

        foreach ($commands as $command) {
            $payload = $command->getPayload();

            foreach (BlogMeasurementEnum::DIMENSIONS_MAPPING as $dimension => $type) {
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

    public function beforeDeleteBlog(EntityDeleteEvent $event): void
    {
        $deletedBlogIds = $event->getIds(BlogDefinition::ENTITY_NAME);

        if ($deletedBlogIds === []) {
            return;
        }

        $deletedIds = [];
        foreach ($deletedBlogIds as $id) {
            $deletedIds[] = \is_string($id) ? $id : $id['id'];
        }

        $parentIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT parent_id
             FROM blog
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
            'DELETE FROM blog_configurator_setting
             WHERE blog_configurator_setting.blog_id IN (:parentIds)
             AND blog_configurator_setting.blog_version_id = :versionId
             AND NOT EXISTS (
                 SELECT 1
                 FROM blog_option po
                 INNER JOIN blog p ON p.id = po.blog_id AND p.version_id = po.blog_version_id
                 WHERE p.parent_id = blog_configurator_setting.blog_id
                     AND p.version_id = :versionId
                     AND po.property_group_option_id = blog_configurator_setting.property_group_option_id
                     AND po.blog_version_id = :versionId
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
     * @param Entity $blog - typehint as Entity because it could be a BlogEntity or PartialEntity
     */
    private function setDefaultLayout(Entity $blog, ?string $channelId = null): void
    {
        if (!$blog->has('cmsPageId')) {
            return;
        }

        if ($blog->get('cmsPageId') !== null) {
            return;
        }

        $cmsPageId = $this->systemConfigService->get(BlogDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_BLOG, $channelId);

        if (!$cmsPageId) {
            return;
        }

        $blog->assign(['cmsPageId' => $cmsPageId]);
    }

    private function convertMeasurementUnit(BlogEntity|PartialEntity $blog): void
    {
        $lengthUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_LENGTH_UNIT);
        $weightUnitHeader = $this->requestStack->getCurrentRequest()?->headers->get(PlatformRequest::HEADER_MEASUREMENT_WEIGHT_UNIT);

        if (!$lengthUnitHeader && !$weightUnitHeader) {
            return;
        }

        $toLengthUnit = $lengthUnitHeader ?? MeasurementUnits::DEFAULT_LENGTH_UNIT;
        $toWeightUnit = $weightUnitHeader ?? MeasurementUnits::DEFAULT_WEIGHT_UNIT;

        $converted = $this->measurementUnitBuilder->build($blog, $toLengthUnit, $toWeightUnit);

        $assigns = [];

        foreach ($converted->getUnits() as $unit => $convertedUnit) {
            $assigns[$unit] = $convertedUnit->value;
        }

        if ($assigns !== []) {
            $blog->assign($assigns);
        }
    }
}
