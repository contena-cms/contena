<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryEvents;
use Contena\Core\Content\Category\Event\CategoryLevelLoaderCacheKeyEvent;
use Contena\Core\Content\Category\Service\CachedDefaultCategoryLevelLoader;
use Contena\Core\Content\Category\Service\DefaultCategoryLevelLoader;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @internal
 */
#[CoversClass(CachedDefaultCategoryLevelLoader::class)]
class CachedDefaultCategoryLevelLoaderTest extends TestCase
{
    private TagAwareCacheInterface&Stub $cache;

    private EventDispatcherInterface $eventDispatcher;

    private DefaultCategoryLevelLoader&Stub $innerLoader;

    private Stub&ChannelContext $channelContext;

    protected function setUp(): void
    {
        $this->cache = static::createStub(TagAwareCacheInterface::class);
        $this->channelContext = static::createStub(ChannelContext::class);

        $this->eventDispatcher = new EventDispatcher();
        $this->innerLoader = static::createStub(DefaultCategoryLevelLoader::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = CachedDefaultCategoryLevelLoader::getSubscribedEvents();

        static::assertIsArray($events);
        static::assertArrayHasKey(CategoryEvents::CATEGORY_WRITTEN_EVENT, $events);
        static::assertSame('invalidateCache', $events[CategoryEvents::CATEGORY_WRITTEN_EVENT]);

        static::assertArrayHasKey(CategoryEvents::CATEGORY_DELETED_EVENT, $events);
        static::assertSame('invalidateCache', $events[CategoryEvents::CATEGORY_DELETED_EVENT]);
    }

    public function testLoadLevelsOutsideMainCategoryIsUncached(): void
    {
        $rootId = 'non-navigation-category-id';
        $rootLevel = 1;
        $depth = 3;
        $criteria = new Criteria();

        $channel = new ChannelEntity()->assign([
            'navigationCategoryId' => 'different-id',
        ]);

        $this->channelContext->method('getChannel')
            ->willReturn($channel);

        $expectedCollection = new CategoryCollection();

        $innerLoader = $this->createMock(DefaultCategoryLevelLoader::class);
        $innerLoader->expects($this->once())
            ->method('loadLevels')
            ->with($rootId, $rootLevel, $this->channelContext, $criteria, $depth)
            ->willReturn($expectedCollection);

        $result = $this->createLoader(innerLoader: $innerLoader)->loadLevels(
            $rootId,
            $rootLevel,
            $this->channelContext,
            $criteria,
            $depth
        );

        static::assertSame($expectedCollection, $result);
    }

    public function testInvalidateCache(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())
            ->method('invalidateTags')
            ->with(['category_level_loader']);

        $this->createLoader(cache: $cache)->invalidateCache();
    }

    public function testCachedLoading(): void
    {
        $rootId = 'navigation-category-id';
        $rootLevel = 1;
        $depth = 3;
        $criteria = new Criteria();

        $channel = new ChannelEntity()->assign([
            'navigationCategoryId' => $rootId,
        ]);

        $this->channelContext->method('getChannel')
            ->willReturn($channel);
        $context = Context::createDefaultContext();
        $this->channelContext->method('getContext')
            ->willReturn($context);
        $this->channelContext->method('getChannelId')
            ->willReturn('channel-id');

        $expectedCollection = new CategoryCollection();
        $innerLoader = $this->createMock(DefaultCategoryLevelLoader::class);
        $innerLoader->expects($this->exactly(1))
            ->method('loadLevels')
            ->with($rootId, $rootLevel, $this->channelContext, $criteria, $depth)
            ->willReturn($expectedCollection);

        $cache = new TagAwareAdapter(new ArrayAdapter());

        $loader = new CachedDefaultCategoryLevelLoader(
            $cache,
            $this->eventDispatcher,
            $innerLoader,
        );

        $cacheKeyParts = [
            'rootId' => $rootId,
            'depth' => $depth,
            'channelId' => 'channel-id',
            'languageId' => $context->getLanguageId(),
        ];
        $eventsThrown = 0;
        $this->eventDispatcher->addListener(
            CategoryLevelLoaderCacheKeyEvent::class,
            static function (CategoryLevelLoaderCacheKeyEvent $event) use ($cacheKeyParts, &$eventsThrown): void {
                static::assertSame($cacheKeyParts, $event->getParts());

                ++$eventsThrown;
            }
        );

        $result = $loader->loadLevels(
            $rootId,
            $rootLevel,
            $this->channelContext,
            $criteria,
            $depth
        );
        $result2 = $loader->loadLevels(
            $rootId,
            $rootLevel,
            $this->channelContext,
            $criteria,
            $depth
        );

        // the first call built the levels itself and returns them without a cache round trip
        static::assertSame($expectedCollection, $result);
        static::assertEquals($result2, $result);
        static::assertSame(2, $eventsThrown);

        static::assertTrue($cache->hasItem(Hasher::hash($cacheKeyParts)));

        $loader->invalidateCache();

        static::assertFalse($cache->hasItem(Hasher::hash($cacheKeyParts)));
    }

    public function testEventDisablesCaching(): void
    {
        $rootId = 'navigation-category-id';
        $rootLevel = 1;
        $depth = 3;
        $criteria = new Criteria();

        $channel = new ChannelEntity()->assign([
            'navigationCategoryId' => $rootId,
        ]);

        $this->channelContext->method('getChannel')
            ->willReturn($channel);
        $context = Context::createDefaultContext();
        $this->channelContext->method('getContext')
            ->willReturn($context);
        $this->channelContext->method('getChannelId')
            ->willReturn('channel-id');

        $expectedCollection = new CategoryCollection();
        $innerLoader = $this->createMock(DefaultCategoryLevelLoader::class);
        $innerLoader->expects($this->exactly(1))
            ->method('loadLevels')
            ->with($rootId, $rootLevel, $this->channelContext, $criteria, $depth)
            ->willReturn($expectedCollection);

        $cache = new TagAwareAdapter(new ArrayAdapter());

        $loader = new CachedDefaultCategoryLevelLoader(
            $cache,
            $this->eventDispatcher,
            $innerLoader,
        );

        $cacheKeyParts = [
            'rootId' => $rootId,
            'depth' => $depth,
            'channelId' => 'channel-id',
            'languageId' => $context->getLanguageId(),
        ];
        $eventsThrown = 0;
        $this->eventDispatcher->addListener(
            CategoryLevelLoaderCacheKeyEvent::class,
            static function (CategoryLevelLoaderCacheKeyEvent $event) use ($cacheKeyParts, &$eventsThrown): void {
                static::assertSame($cacheKeyParts, $event->getParts());

                $event->disableCaching();

                ++$eventsThrown;
            }
        );

        $result = $loader->loadLevels(
            $rootId,
            $rootLevel,
            $this->channelContext,
            $criteria,
            $depth
        );

        static::assertEquals($expectedCollection, $result);
        static::assertSame(1, $eventsThrown);

        static::assertFalse($cache->hasItem(Hasher::hash($cacheKeyParts)));
    }

    public function testEventManipulatesCacheKey(): void
    {
        $rootId = 'navigation-category-id';
        $rootLevel = 1;
        $depth = 3;
        $criteria = new Criteria();

        $channel = new ChannelEntity()->assign([
            'navigationCategoryId' => $rootId,
        ]);

        $this->channelContext->method('getChannel')
            ->willReturn($channel);
        $context = Context::createDefaultContext();
        $this->channelContext->method('getContext')
            ->willReturn($context);
        $this->channelContext->method('getChannelId')
            ->willReturn('channel-id');

        $expectedCollection = new CategoryCollection();
        $innerLoader = $this->createMock(DefaultCategoryLevelLoader::class);
        $innerLoader->expects($this->exactly(1))
            ->method('loadLevels')
            ->with($rootId, $rootLevel, $this->channelContext, $criteria, $depth)
            ->willReturn($expectedCollection);

        $cache = new TagAwareAdapter(new ArrayAdapter());

        $loader = new CachedDefaultCategoryLevelLoader(
            $cache,
            $this->eventDispatcher,
            $innerLoader,
        );

        $cacheKeyParts = [
            'rootId' => $rootId,
            'depth' => $depth,
            'channelId' => 'channel-id',
            'languageId' => $context->getLanguageId(),
        ];
        $eventsThrown = 0;
        $this->eventDispatcher->addListener(
            CategoryLevelLoaderCacheKeyEvent::class,
            static function (CategoryLevelLoaderCacheKeyEvent $event) use ($cacheKeyParts, &$eventsThrown): void {
                static::assertSame($cacheKeyParts, $event->getParts());

                $event->addPart('test', 'test');

                ++$eventsThrown;
            }
        );

        $result = $loader->loadLevels(
            $rootId,
            $rootLevel,
            $this->channelContext,
            $criteria,
            $depth
        );

        static::assertEquals($expectedCollection, $result);
        static::assertSame(1, $eventsThrown);

        $cacheKeyParts['test'] = 'test';
        static::assertTrue($cache->hasItem(Hasher::hash($cacheKeyParts)));

        $loader->invalidateCache();

        static::assertFalse($cache->hasItem(Hasher::hash($cacheKeyParts)));
    }

    private function createLoader(
        ?TagAwareCacheInterface $cache = null,
        ?DefaultCategoryLevelLoader $innerLoader = null,
    ): CachedDefaultCategoryLevelLoader {
        return new CachedDefaultCategoryLevelLoader(
            $cache ?? $this->cache,
            $this->eventDispatcher,
            $innerLoader ?? $this->innerLoader,
        );
    }
}
