<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Cache;

use Contena\Core\Content\Blog\Aggregate\BlogContentLayout\BlogContentLayoutDefinition;
use Contena\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\ContentSystem\Cache\CacheInvalidationSubscriber;
use Contena\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Contena\Core\Framework\ContentSystem\ContentSection;
use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CacheInvalidationSubscriber::class)]
class CacheInvalidationSubscriberTest extends TestCase
{
    private const HEADER_ASSIGNMENT = 'test_header_assignment';

    private const FOOTER_ASSIGNMENT = 'test_footer_assignment';

    private CacheInvalidator&MockObject $cacheInvalidator;

    private Connection&Stub $connection;

    private EntityCacheTagResolver&Stub $cacheTagResolver;

    private DefinitionInstanceRegistry&Stub $definitionRegistry;

    private CacheInvalidationSubscriber $subscriber;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->cacheInvalidator = static::createMock(CacheInvalidator::class);
        $this->connection = static::createStub(Connection::class);
        $this->cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $this->definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $this->ids = new IdsCollection();

        $this->subscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            $this->cacheTagResolver,
            $this->definitionRegistry,
            [
                self::HEADER_ASSIGNMENT => ContentSection::HEADER->value,
                self::FOOTER_ASSIGNMENT => ContentSection::FOOTER->value,
            ],
        );
    }

    #[TestDox('invalidates layout cache tag when content_layout entity is written')]
    public function testInvalidatesLayoutCacheTagOnContentLayoutWrite(): void
    {
        $layoutId = 'layout-id';

        $event = $this->createWrittenEvent(ContentLayoutDefinition::ENTITY_NAME, $layoutId);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([ContentSection::MAIN->buildLayoutTag($layoutId)]);

        ($this->subscriber)($event);
    }

    #[DataProvider('invalidatesEntityAssignmentCacheTagProvider')]
    #[TestDox('invalidates entity assignment cache tag for $entityName')]
    public function testInvalidatesEntityAssignmentCacheTag(string $entityName, string $tagPrefix): void
    {
        $assignmentId = $this->ids->get('assignment');
        $entityId = 'entity-id';

        $event = $this->createWrittenEvent($entityName, $assignmentId);

        $this->connection->method('fetchFirstColumn')
            ->willReturn([$entityId]);

        $this->definitionRegistry->method('get')
            ->willReturn(static::createStub(EntityDefinition::class));

        $this->cacheTagResolver->method('resolve')
            ->willReturn($tagPrefix . $entityId);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([$tagPrefix . $entityId]);

        ($this->subscriber)($event);
    }

    #[DataProvider('invalidatesSectionCacheTagProvider')]
    #[TestDox('invalidates $section cache tags when $entityName is written')]
    public function testInvalidatesSectionCacheTag(string $entityName, ContentSection $section): void
    {
        $assignmentId = $this->ids->get('assignment');
        $layoutId = 'layout-id';

        $event = $this->createWrittenEvent($entityName, $assignmentId);

        $this->connection->method('fetchFirstColumn')
            ->willReturn([$layoutId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with($section->buildRouteCacheTags($layoutId));

        ($this->subscriber)($event);
    }

    #[TestDox('filters null cache tags from entity invalidation')]
    public function testFiltersNullCacheTagsFromEntityInvalidation(): void
    {
        $assignmentIdA = $this->ids->get('assignment-a');
        $assignmentIdB = $this->ids->get('assignment-b');
        $blogIdA = 'entity-a';
        $blogIdB = 'entity-b';

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(BlogContentLayoutDefinition::ENTITY_NAME, [
                    new EntityWriteResult($assignmentIdA, [], BlogContentLayoutDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
                    new EntityWriteResult($assignmentIdB, [], BlogContentLayoutDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
                ], Context::createDefaultContext()),
            ]),
            [],
        );

        $this->connection->method('fetchFirstColumn')
            ->willReturn([$blogIdA, $blogIdB]);

        $this->definitionRegistry->method('get')
            ->willReturn(static::createStub(EntityDefinition::class));

        $this->cacheTagResolver->method('resolve')
            ->willReturnCallback(fn (EntityDefinition $def, string $id) => match ($id) {
                $blogIdA => 'blog-' . $blogIdA,
                default => null,
            });

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(['blog-' . $blogIdA]);

        ($this->subscriber)($event);
    }

    #[TestDox('skips section invalidation when no layout IDs are found in database')]
    public function testSkipsSectionInvalidationWhenNoLayoutIdsFound(): void
    {
        $event = $this->createWrittenEvent(self::HEADER_ASSIGNMENT, $this->ids->get('assignment'));

        $this->connection->method('fetchFirstColumn')
            ->willReturn([]);

        $this->cacheInvalidator->expects($this->never())
            ->method('invalidate');

        ($this->subscriber)($event);
    }

    #[TestDox('skips cache invalidation when no relevant entities are written')]
    public function testSkipsCacheInvalidationWhenNoRelevantEntitiesWritten(): void
    {
        $event = $this->createWrittenEvent('order', 'order-id');

        $this->cacheInvalidator->expects($this->never())
            ->method('invalidate');

        ($this->subscriber)($event);
    }

    #[TestDox('skips entity invalidation when no assignment IDs are found in database')]
    public function testSkipsEntityInvalidationWhenNoAssignmentIdsFound(): void
    {
        $event = $this->createWrittenEvent(BlogContentLayoutDefinition::ENTITY_NAME, $this->ids->get('assignment'));

        $this->connection->method('fetchFirstColumn')
            ->willReturn([]);

        $this->cacheInvalidator->expects($this->never())
            ->method('invalidate');

        ($this->subscriber)($event);
    }

    #[TestDox('ignores a section assignment table that no bundle registered')]
    public function testIgnoresUnregisteredSectionAssignment(): void
    {
        $subscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            $this->cacheTagResolver,
            $this->definitionRegistry,
            [],
        );

        $event = $this->createWrittenEvent(self::HEADER_ASSIGNMENT, $this->ids->get('assignment'));

        $this->cacheInvalidator->expects($this->never())
            ->method('invalidate');

        $subscriber($event);
    }

    #[TestDox('rejects a section assignment map naming an unknown section')]
    public function testRejectsUnknownSection(): void
    {
        $this->cacheInvalidator->expects($this->never())
            ->method('invalidate');

        static::expectException(\ValueError::class);

        new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            $this->cacheTagResolver,
            $this->definitionRegistry,
            ['sidebar_content_layout' => 'sidebar'],
        );
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function invalidatesEntityAssignmentCacheTagProvider(): \Generator
    {
        yield 'blog assignment' => [BlogContentLayoutDefinition::ENTITY_NAME, 'blog-'];
        yield 'category assignment' => [CategoryContentLayoutDefinition::ENTITY_NAME, 'category-route-'];
        yield 'landing page assignment' => [LandingPageContentLayoutDefinition::ENTITY_NAME, 'landing-page-route-'];
    }

    /**
     * @return \Generator<string, array{string, ContentSection}>
     */
    public static function invalidatesSectionCacheTagProvider(): \Generator
    {
        yield 'header section' => [self::HEADER_ASSIGNMENT, ContentSection::HEADER];
        yield 'footer section' => [self::FOOTER_ASSIGNMENT, ContentSection::FOOTER];
    }

    private function createWrittenEvent(string $entityName, string $id): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent($entityName, [
                    new EntityWriteResult($id, [], $entityName, EntityWriteResult::OPERATION_INSERT),
                ], Context::createDefaultContext()),
            ]),
            [],
        );
    }
}
