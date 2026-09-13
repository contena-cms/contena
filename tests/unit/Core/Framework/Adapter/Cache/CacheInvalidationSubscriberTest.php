<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Cache;

use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Content\Seo\Event\SeoUrlUpdateEvent;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\CachedSystemConfigLoader;
use Contena\Core\System\SystemConfig\Event\SystemConfigMultipleChangedEvent;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CacheInvalidationSubscriber::class)]
class CacheInvalidationSubscriberTest extends TestCase
{
    public function testGlobalChangeInvalidatesObjectAndAllDependentHttpCaches(): void
    {
        $invalidator = $this->createMock(CacheInvalidator::class);
        $expects = $this->exactly(2);
        $invalidator->expects($expects)
            ->method('invalidate')
            ->willReturnCallback(static function (array $tags, bool $force = false) use ($expects): void {
                if ($expects->numberOfInvocations() === 1) {
                    static::assertSame([CachedSystemConfigLoader::CACHE_TAG], $tags);
                    static::assertTrue($force);

                    return;
                }

                static::assertSame(['system.config-'], $tags);
                static::assertFalse($force);
            });

        new CacheInvalidationSubscriber($invalidator, static::createStub(Connection::class))->invalidateConfigKey(
            new SystemConfigMultipleChangedEvent(['example.config.enabled' => true], null, false)
        );
    }

    public function testSilentChangeOnlyInvalidatesObjectCache(): void
    {
        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects($this->once())
            ->method('invalidate')
            ->with([CachedSystemConfigLoader::CACHE_TAG], true);

        new CacheInvalidationSubscriber($invalidator, static::createStub(Connection::class))->invalidateConfigKey(
            new SystemConfigMultipleChangedEvent(
                ['example.config.enabled' => true],
                null,
                true,
            )
        );
    }

    public function testInvalidateCategoryRouteBySeoUrlChanges(): void
    {
        $seoUrlId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchFirstColumn')->willReturn([$categoryId]);

        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects($this->once())
            ->method('invalidate')
            ->with([CategoryRoute::buildName($categoryId)]);

        new CacheInvalidationSubscriber($invalidator, $connection)->invalidateCategoryRouteBySeoUrlChanges(
            $this->createSeoUrlWrittenEvent([$seoUrlId], $context)
        );
    }

    public function testInvalidateCategoryRouteBySeoUrlUpdate(): void
    {
        $categoryId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchFirstColumn')->willReturn([$categoryId]);

        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects($this->once())
            ->method('invalidate')
            ->with([CategoryRoute::buildName($categoryId)]);

        $event = new SeoUrlUpdateEvent(
            [
                ['foreignKey' => $categoryId, 'seoPathInfo' => 'new-path/'],
                ['foreignKey' => $categoryId, 'seoPathInfo' => 'new-path/', 'channelId' => Uuid::randomHex()],
            ],
            Context::createDefaultContext()
        );

        new CacheInvalidationSubscriber($invalidator, $connection)->invalidateCategoryRouteBySeoUrlUpdate($event);
    }

    public function testDoesNotInvalidateCategoryRouteForSeoUrlUpdatesOfOtherEntities(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchFirstColumn')->willReturn([]);

        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects($this->never())->method('invalidate');

        $event = new SeoUrlUpdateEvent(
            [['foreignKey' => Uuid::randomHex()]],
            Context::createDefaultContext()
        );

        new CacheInvalidationSubscriber($invalidator, $connection)->invalidateCategoryRouteBySeoUrlUpdate($event);
    }

    public function testSeoUrlUpdateWithoutUsableForeignKeysIsIgnored(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects($this->never())->method('invalidate');

        $event = new SeoUrlUpdateEvent(
            [
                ['seoPathInfo' => 'no-foreign-key/'],
                ['foreignKey' => 'not-a-uuid'],
                ['foreignKey' => null],
            ],
            Context::createDefaultContext()
        );

        new CacheInvalidationSubscriber($invalidator, $connection)->invalidateCategoryRouteBySeoUrlUpdate($event);
    }

    public function testDoesNotInvalidateCategoryRouteWhenNoSeoUrlWasWritten(): void
    {
        $context = Context::createDefaultContext();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects($this->never())->method('invalidate');

        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityWrittenEvent(
                    CategoryTranslationDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            ['categoryId' => Uuid::randomHex(), 'languageId' => Uuid::randomHex()],
                            ['name' => 'new name'],
                            CategoryTranslationDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
            ]),
            [],
        );

        new CacheInvalidationSubscriber($invalidator, $connection)->invalidateCategoryRouteBySeoUrlChanges($event);
    }

    public function testDoesNotInvalidateCategoryRouteForSeoUrlsOfOtherEntities(): void
    {
        $context = Context::createDefaultContext();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchFirstColumn')->willReturn([]);

        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects($this->never())->method('invalidate');

        new CacheInvalidationSubscriber($invalidator, $connection)->invalidateCategoryRouteBySeoUrlChanges(
            $this->createSeoUrlWrittenEvent([Uuid::randomHex()], $context)
        );
    }

    /**
     * @param list<string> $seoUrlIds
     */
    private function createSeoUrlWrittenEvent(array $seoUrlIds, Context $context): EntityWrittenContainerEvent
    {
        $writeResults = array_map(
            static fn (string $id) => new EntityWriteResult(
                $id,
                ['seoPathInfo' => 'new-path/'],
                SeoUrlDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE,
            ),
            $seoUrlIds
        );

        return new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityWrittenEvent(SeoUrlDefinition::ENTITY_NAME, $writeResults, $context),
            ]),
            [],
        );
    }
}
