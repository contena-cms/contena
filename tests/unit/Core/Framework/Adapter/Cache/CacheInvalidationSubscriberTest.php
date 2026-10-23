<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Content\Category\Channel\NavigationRoute;
use Contena\Core\Content\Media\Event\MediaIndexerEvent;
use Contena\Core\Content\Media\Channel\MediaRoute;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs\BlogCrossSellingAssignedBlogsDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingTranslation\BlogCrossSellingTranslationDefinition;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Snippet\SnippetDefinition;
use Contena\Core\System\SystemConfig\CachedSystemConfigLoader;
use Contena\Core\System\SystemConfig\Event\SystemConfigChangedHook;

/**
 * @internal
 */
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

    public function testConsidersKeyOfCachedBaseChannelContextFactoryForInvalidatingContext(): void
    {
        $channelId = Uuid::randomHex();

        $this->connection->expects($this->never())->method('fetchAllAssociative');

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(
                [
                    'context-factory-' . $channelId,
                    'base-context-factory-' . $channelId,
                ],
                true
            );

        $subscriber = $this->createSubscriber();

        $subscriber->invalidateContext(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(
                    ChannelDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $channelId,
                            [],
                            ChannelDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    Context::createDefaultContext(),
                ),
            ]),
            [],
        ));
    }

    public function testInvalidateMediaWithoutVariantsWillInvalidateOnlyBlogs(): void
    {
        $blogId = '123';
        $mediaId = Uuid::randomHex();
        $event = new MediaIndexerEvent([$mediaId], Context::createDefaultContext(), []);

        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([['blog_id' => $blogId, 'version_id' => null]]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(
                [
                    EntityCacheKeyGenerator::buildBlogTag($blogId),
                    MediaRoute::buildName($mediaId),
                ],
                false
            );

        $subscriber->invalidateMedia($event);
    }

    public function testInvalidateMediaWithVariantsWillInvalidateBlogsAndVariants(): void
    {
        $blogId = '123';
        $variants = ['456', '789'];
        $mediaId = Uuid::randomHex();
        $event = new MediaIndexerEvent([$mediaId], Context::createDefaultContext(), []);

        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['blog_id' => $blogId, 'variant_id' => $variants[0]],
                ['blog_id' => $blogId, 'variant_id' => $variants[1]],
            ]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(
                [
                    EntityCacheKeyGenerator::buildBlogTag($blogId),
                    EntityCacheKeyGenerator::buildBlogTag($variants[0]),
                    EntityCacheKeyGenerator::buildBlogTag($variants[1]),
                    MediaRoute::buildName($mediaId),
                ],
                false
            );

        $subscriber->invalidateMedia($event);
    }

    public function testInvalidateNavigationRouteWithChannelSettings(): void
    {
        $channelId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->never())->method('fetchAllAssociative');

        // Test when sales channel navigation settings change
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityWrittenEvent(
                    ChannelDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $channelId,
                            [
                                'navigationCategoryId' => Uuid::randomHex(),
                                'navigationCategoryDepth' => 3,
                            ],
                            ChannelDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
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
                new EntityWrittenEvent(
                    CategoryDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $categoryId,
                            [
                                'parentId' => Uuid::randomHex(),
                                'visible' => true,
                                'active' => false,
                            ],
                            CategoryDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
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
                new EntityWrittenEvent(
                    CategoryDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $categoryId,
                            [],
                            CategoryDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_DELETE,
                        ),
                    ],
                    $context,
                ),
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
                new EntityWrittenEvent(
                    CategoryTranslationDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $categoryTranslationId,
                            [
                                'name' => 'New Category Name',
                            ],
                            CategoryTranslationDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
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
                new EntityWrittenEvent(
                    CategoryTranslationDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $categoryTranslationId,
                            [
                                'slotConfig' => ['slot-id' => ['content' => ['value' => 'new content']]],
                            ],
                            CategoryTranslationDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
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
                new EntityWrittenEvent(
                    CategoryTranslationDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            ['categoryId' => Uuid::randomHex(), 'languageId' => Uuid::randomHex()],
                            ['metaDescription' => 'new description'],
                            CategoryTranslationDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
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
                new EntityWrittenEvent(
                    CategoryTranslationDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            ['languageId' => Uuid::randomHex()],
                            ['slotConfig' => ['slot-id' => ['content' => ['value' => 'new content']]]],
                            CategoryTranslationDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
            ]),
            [],
        );

        $this->cacheInvalidator->expects($this->never())->method('invalidate');

        $this->createSubscriber()->invalidateCategoryRouteByCategoryTranslationChanges($event);
    }

    public function testInvalidateNavigationRouteWithMultipleTriggers(): void
    {
        $channelId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->never())->method('fetchAllAssociative');

        // Test when both sales channel settings and category data change
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityWrittenEvent(
                    ChannelDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $channelId,
                            [
                                'footerCategoryId' => Uuid::randomHex(),
                            ],
                            ChannelDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
                new EntityWrittenEvent(
                    CategoryDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $categoryId,
                            [
                                'active' => true,
                            ],
                            CategoryDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
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
                new EntityWrittenEvent(
                    CategoryDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $categoryId,
                            [
                                'description' => 'New description',
                                'metaTitle' => 'New meta title',
                            ],
                            CategoryDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
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
        $channelId = Uuid::randomHex();

        $subscriber = $this->createSubscriber();
        $this->connection->expects($this->never())->method('fetchAllAssociative');

        $expects = $this->exactly(2);
        $this->cacheInvalidator->expects($expects)
            ->method('invalidate')
            ->willReturnCallback(static function (array $tags, bool $immediate = false) use ($expects, $channelId): void {
                match ($expects->numberOfInvocations()) {
                    1 => static::assertSame([CachedSystemConfigLoader::CACHE_TAG], $tags),
                    2 => static::assertSame(['system.config-' . $channelId], $tags),
                    default => static::fail('Unexpected invocation'),
                };
            });

        $subscriber->invalidateConfigKey(new SystemConfigChangedHook([], [], $channelId, false));
    }

    public function testInvalidateStreamIdsWithBlogStreamFilterWrites(): void
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
                new EntityWrittenEvent(
                    'content_stream_filter',
                    [
                        new EntityWriteResult(
                            Uuid::randomHex(),
                            ['contentStreamId' => $streamId],
                            'content_stream_filter',
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                        new EntityWriteResult(
                            Uuid::randomHex(),
                            [],
                            'content_stream_filter',
                            EntityWriteResult::OPERATION_DELETE,
                            new EntityExistence(
                                'content_stream_filter',
                                ['id' => Uuid::fromHexToBytes(Uuid::randomHex())],
                                true,
                                false,
                                false,
                                ['content_stream_id' => Uuid::fromHexToBytes($deletedStreamId)]
                            ),
                        ),
                    ],
                    Context::createDefaultContext(),
                ),
            ]),
            [],
        ));
    }

    public function testInvalidateBlogCrossSellingInvalidatesOwningBlog(): void
    {
        $crossSellingId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $subscriber = $this->createSubscriber();

        $this->connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([$blogId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([BlogDetailRoute::buildName($blogId)], true);

        $subscriber->invalidateBlogCrossSelling(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(
                    BlogCrossSellingDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $crossSellingId,
                            ['active' => true],
                            BlogCrossSellingDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    Context::createDefaultContext(),
                ),
            ]),
            [],
        ));
    }

    public function testInvalidateBlogCrossSellingInvalidatesAssignedBlogOwner(): void
    {
        $assignedBlogId = Uuid::randomHex();
        $crossSellingId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $subscriber = $this->createSubscriber();

        $this->connection->expects($this->exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnOnConsecutiveCalls([$crossSellingId], [$blogId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([BlogDetailRoute::buildName($blogId)], true);

        $subscriber->invalidateBlogCrossSelling(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(
                    BlogCrossSellingAssignedBlogsDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $assignedBlogId,
                            ['blogId' => Uuid::randomHex()],
                            BlogCrossSellingAssignedBlogsDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_INSERT,
                        ),
                    ],
                    Context::createDefaultContext(),
                ),
            ]),
            [],
        ));
    }

    public function testInvalidateBlogCrossSellingUsesAssignedBlogPayload(): void
    {
        $assignedBlogId = Uuid::randomHex();
        $crossSellingId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $subscriber = $this->createSubscriber();

        $this->connection->expects($this->exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnOnConsecutiveCalls([$crossSellingId], [$blogId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([BlogDetailRoute::buildName($blogId)], true);

        $subscriber->invalidateBlogCrossSelling(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(
                    BlogCrossSellingAssignedBlogsDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $assignedBlogId,
                            ['crossSellingId' => $crossSellingId],
                            BlogCrossSellingAssignedBlogsDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_INSERT,
                        ),
                    ],
                    Context::createDefaultContext(),
                ),
            ]),
            [],
        ));
    }

    public function testInvalidateBlogCrossSellingNormalizesBinaryTranslationPrimaryKey(): void
    {
        $crossSellingId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $subscriber = $this->createSubscriber();

        $this->connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([$blogId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([BlogDetailRoute::buildName($blogId)], true);

        $subscriber->invalidateBlogCrossSelling(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(
                    BlogCrossSellingTranslationDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            [
                                'blogCrossSellingId' => Uuid::fromHexToBytes($crossSellingId),
                                'languageId' => Uuid::randomHex(),
                            ],
                            ['name' => 'Accessories'],
                            BlogCrossSellingTranslationDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_DELETE,
                        ),
                    ],
                    Context::createDefaultContext(),
                ),
            ]),
            [],
        ));
    }

    public function testInvalidateBlogCrossSellingInvalidatesTranslationOwner(): void
    {
        $crossSellingId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $subscriber = $this->createSubscriber();

        $this->connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([$blogId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([BlogDetailRoute::buildName($blogId)], true);

        $subscriber->invalidateBlogCrossSelling(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(
                    BlogCrossSellingTranslationDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            [
                                'blogCrossSellingId' => $crossSellingId,
                                'languageId' => Uuid::randomHex(),
                            ],
                            ['name' => 'Accessories'],
                            BlogCrossSellingTranslationDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    Context::createDefaultContext(),
                ),
            ]),
            [],
        ));
    }

    public function testInvalidateBlogCrossSellingBeforeDeletionInvalidatesAfterSuccessfulDelete(): void
    {
        $assignedBlogId = Uuid::randomHex();
        $crossSellingId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $subscriber = $this->createSubscriber();
        $event = $this->createMock(EntityDeleteEvent::class);

        $event->method('getIds')
            ->willReturnMap([
                [BlogCrossSellingDefinition::ENTITY_NAME, []],
                [BlogCrossSellingAssignedBlogsDefinition::ENTITY_NAME, [$assignedBlogId]],
                [BlogCrossSellingTranslationDefinition::ENTITY_NAME, []],
            ]);

        $this->connection->expects($this->exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnOnConsecutiveCalls([$crossSellingId], [$blogId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([BlogDetailRoute::buildName($blogId)], true);

        $event->expects($this->once())
            ->method('addSuccess')
            ->willReturnCallback(static function (\Closure $callback): void {
                $callback();
            });

        $subscriber->invalidateBlogCrossSellingBeforeDeletion($event);
    }

    public function createSnippetEvent(): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(
                    SnippetDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            Uuid::randomHex(),
                            [
                                'translationKey' => 'test',
                            ],
                            SnippetDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    Context::createDefaultContext(),
                ),
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
