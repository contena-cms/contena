<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\SalesChannel\CategoryRoute;
use Contena\Core\Content\Category\SalesChannel\NavigationRoute;
use Contena\Core\Content\Media\Event\MediaIndexerEvent;
use Contena\Core\Content\Media\SalesChannel\MediaRoute;
use Contena\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Contena\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsDefinition;
use Contena\Core\Content\Product\Aggregate\ProductCrossSellingTranslation\ProductCrossSellingTranslationDefinition;
use Contena\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\CachedSystemConfigLoader;
use Contena\Core\System\SystemConfig\Event\SystemConfigChangedHook;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheInvalidationSubscriber::class)]
#[Group('cache')]
class CacheInvalidationSubscriberTest extends TestCase
{
    /**
     * @var CacheInvalidator&MockObject
     */
    private CacheInvalidator $cacheInvalidator;

    /**
     * @var Connection&MockObject
     */
    private Connection $connection;

    protected function setUp(): void
    {
        $this->cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $this->connection = $this->createMock(Connection::class);
    }

    public function testConsidersKeyOfCachedBaseSalesChannelContextFactoryForInvalidatingContext(): void
    {
        $salesChannelId = Uuid::randomHex();

        $this->connection->expects($this->never())->method('fetchAllAssociative');

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(
                [
                    'context-factory-' . $salesChannelId,
                    'base-context-factory-' . $salesChannelId,
                ],
                true
            );

        $subscriber = $this->createSubscriber();

        $subscriber->invalidateContext(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
            ]),
            [],
        ));
    }

    public function testInvalidateMediaWithoutVariantsWillInvalidateOnlyProducts(): void
    {
        $productId = '123';
        $mediaId = Uuid::randomHex();
        $event = new MediaIndexerEvent([$mediaId], Context::createDefaultContext(), []);

        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([['product_id' => $productId, 'version_id' => null]]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(
                [
                    EntityCacheKeyGenerator::buildProductTag($productId),
                    MediaRoute::buildName($mediaId),
                ],
                false
            );

        $subscriber->invalidateMedia($event);
    }

    public function testInvalidateMediaWithVariantsWillInvalidateProductsAndVariants(): void
    {
        $productId = '123';
        $variants = ['456', '789'];
        $mediaId = Uuid::randomHex();
        $event = new MediaIndexerEvent([$mediaId], Context::createDefaultContext(), []);

        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['product_id' => $productId, 'variant_id' => $variants[0]],
                ['product_id' => $productId, 'variant_id' => $variants[1]],
            ]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(
                [
                    EntityCacheKeyGenerator::buildProductTag($productId),
                    EntityCacheKeyGenerator::buildProductTag($variants[0]),
                    EntityCacheKeyGenerator::buildProductTag($variants[1]),
                    MediaRoute::buildName($mediaId),
                ],
                false
            );

        $subscriber->invalidateMedia($event);
    }

    public function testInvalidateNavigationRouteWithSalesChannelSettings(): void
    {
        $salesChannelId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->never())->method('fetchAllAssociative');

        // Test when sales channel navigation settings change
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
            ]),
            [],
        );

        $this->cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([NavigationRoute::ALL_TAG]);

        $subscriber->invalidateNavigationRoute($event);
    }

    public function testInvalidateNavigationRouteWithCategoryStructuralChanges(): void
    {
        $categoryId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->never())->method('fetchAllAssociative');

        // Test when category structural data changes (parentId, visible, active, afterCategoryId)
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
            ]),
            [],
        );

        $this->cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([NavigationRoute::ALL_TAG]);

        $subscriber->invalidateNavigationRoute($event);
    }

    public function testInvalidateNavigationRouteWithDeletedCategories(): void
    {
        $categoryId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->never())->method('fetchAllAssociative');

        // Test when categories are deleted
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
            ]),
            [],
        );

        $this->cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([NavigationRoute::ALL_TAG]);

        $subscriber->invalidateNavigationRoute($event);
    }

    public function testInvalidateNavigationRouteWithCategoryTranslationChanges(): void
    {
        $categoryTranslationId = ['categoryId' => Uuid::randomHex(), 'languageId' => Uuid::randomHex()];
        $context = Context::createDefaultContext();

        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->never())->method('fetchAllAssociative');

        // Test when category translation name changes
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
            ]),
            [],
        );

        $this->cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([NavigationRoute::ALL_TAG]);

        $subscriber->invalidateNavigationRoute($event);
    }

    public function testInvalidateCategoryRouteForCategoryTranslationSlotConfigChanges(): void
    {
        $categoryId = Uuid::randomHex();
        $categoryTranslationId = ['categoryId' => $categoryId, 'languageId' => Uuid::randomHex()];
        $context = Context::createDefaultContext();
        $this->connection->expects($this->never())->method('fetchAllAssociative');

        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
            ]),
            [],
        );

        $this->cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([CategoryRoute::buildName($categoryId)]);

        $this->createSubscriber()->invalidateCategoryRouteByCategoryTranslationChanges($event);
    }

    public function testDoesNotInvalidateCategoryRouteForOtherCategoryTranslationChanges(): void
    {
        $context = Context::createDefaultContext();
        $this->connection->expects($this->never())->method('fetchAllAssociative');
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
            ]),
            [],
        );

        $this->cacheInvalidator->expects($this->never())->method('invalidate');

        $this->createSubscriber()->invalidateCategoryRouteByCategoryTranslationChanges($event);
    }

    public function testDoesNotInvalidateCategoryRouteWhenChangedTranslationHasNoCategoryId(): void
    {
        $context = Context::createDefaultContext();
        $this->connection->expects($this->never())->method('fetchAllAssociative');
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
            ]),
            [],
        );

        $this->cacheInvalidator->expects($this->never())->method('invalidate');

        $this->createSubscriber()->invalidateCategoryRouteByCategoryTranslationChanges($event);
    }

    public function testInvalidateNavigationRouteWithMultipleTriggers(): void
    {
        $salesChannelId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->never())->method('fetchAllAssociative');

        // Test when both sales channel settings and category data change
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
            ]),
            [],
        );

        // Should still only invalidate once with the ALL_TAG when sales channel settings change
        $this->cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([NavigationRoute::ALL_TAG]);

        $subscriber->invalidateNavigationRoute($event);
    }

    public function testInvalidateNavigationRouteWithNoRelevantChanges(): void
    {
        $categoryId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->never())->method('fetchAllAssociative');

        // Test when category data changes that don't affect navigation (e.g., description)
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
            ]),
            [],
        );

        // Should not invalidate anything
        $this->cacheInvalidator
            ->expects($this->never())
            ->method('invalidate');

        $subscriber->invalidateNavigationRoute($event);
    }

    public function testInvalidateConfigKeyClearsObjectCache(): void
    {
        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->never())->method('fetchAllAssociative');

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([CachedSystemConfigLoader::CACHE_TAG], true);

        $subscriber->invalidateConfigKey(new SystemConfigChangedHook([], [], 'sc-id', true));
    }

    public function testInvalidateConfigKeyInvalidatesHttpCacheWhenNotSilent(): void
    {
        $salesChannelId = Uuid::randomHex();

        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->never())->method('fetchAllAssociative');

        $expects = $this->exactly(2);
        $this->cacheInvalidator->expects($expects)
            ->method('invalidate')
            ->willReturnCallback(static function (array $tags, bool $immediate = false) use ($expects, $salesChannelId): void {
                match ($expects->numberOfInvocations()) {
                    1 => static::assertSame([CachedSystemConfigLoader::CACHE_TAG], $tags),
                    2 => static::assertSame(['system.config-' . $salesChannelId], $tags),
                    default => static::fail('Unexpected invocation'),
                };
            });

        $subscriber->invalidateConfigKey(new SystemConfigChangedHook([], [], $salesChannelId, false));
    }

    public function testInvalidateStreamIdsWithProductStreamFilterWrites(): void
    {
        $streamId = Uuid::randomHex();
        $deletedStreamId = Uuid::randomHex();
        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->never())->method('fetchAllAssociative');

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([
                EntityCacheKeyGenerator::buildStreamTag($streamId),
                EntityCacheKeyGenerator::buildStreamTag($deletedStreamId),
            ]);

        $subscriber->invalidateStreamIds(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
            ]),
            [],
        ));
    }

    public function testInvalidateProductCrossSellingInvalidatesOwningProduct(): void
    {
        $crossSellingId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $subscriber = $this->createSubscriber();

        $this->connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([$productId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([ProductDetailRoute::buildName($productId)], true);

        $subscriber->invalidateProductCrossSelling(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
            ]),
            [],
        ));
    }

    public function testInvalidateProductCrossSellingInvalidatesAssignedProductOwner(): void
    {
        $assignedProductId = Uuid::randomHex();
        $crossSellingId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $subscriber = $this->createSubscriber();

        $this->connection->expects($this->exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnOnConsecutiveCalls([$crossSellingId], [$productId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([ProductDetailRoute::buildName($productId)], true);

        $subscriber->invalidateProductCrossSelling(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
            ]),
            [],
        ));
    }

    public function testInvalidateProductCrossSellingUsesAssignedProductPayload(): void
    {
        $assignedProductId = Uuid::randomHex();
        $crossSellingId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $subscriber = $this->createSubscriber();

        $this->connection->expects($this->exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnOnConsecutiveCalls([$crossSellingId], [$productId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([ProductDetailRoute::buildName($productId)], true);

        $subscriber->invalidateProductCrossSelling(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
            ]),
            [],
        ));
    }

    public function testInvalidateProductCrossSellingNormalizesBinaryTranslationPrimaryKey(): void
    {
        $crossSellingId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $subscriber = $this->createSubscriber();

        $this->connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([$productId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([ProductDetailRoute::buildName($productId)], true);

        $subscriber->invalidateProductCrossSelling(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
            ]),
            [],
        ));
    }

    public function testInvalidateProductCrossSellingInvalidatesTranslationOwner(): void
    {
        $crossSellingId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $subscriber = $this->createSubscriber();

        $this->connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([$productId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([ProductDetailRoute::buildName($productId)], true);

        $subscriber->invalidateProductCrossSelling(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
            ]),
            [],
        ));
    }

    public function testInvalidateProductCrossSellingBeforeDeletionInvalidatesAfterSuccessfulDelete(): void
    {
        $assignedProductId = Uuid::randomHex();
        $crossSellingId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $subscriber = $this->createSubscriber();
        $event = $this->createMock(EntityDeleteEvent::class);

        $event->method('getIds')
            ->willReturnMap([
                [ProductCrossSellingDefinition::ENTITY_NAME, []],
                [ProductCrossSellingAssignedProductsDefinition::ENTITY_NAME, [$assignedProductId]],
                [ProductCrossSellingTranslationDefinition::ENTITY_NAME, []],
            ]);

        $this->connection->expects($this->exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnOnConsecutiveCalls([$crossSellingId], [$productId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([ProductDetailRoute::buildName($productId)], true);

        $event->expects($this->once())
            ->method('addSuccess')
            ->willReturnCallback(static function (\Closure $callback): void {
                $callback();
            });

        $subscriber->invalidateProductCrossSellingBeforeDeletion($event);
    }

    public function createSnippetEvent(): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
            ]),
            [],
        );
    }

    private function createSubscriber(): CacheInvalidationSubscriber
    {
        return new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            true,
        );
    }
}
