<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Frontend\Framework\Routing\CachedDomainLoader;
use Contena\Frontend\Framework\Routing\CachedDomainLoaderInvalidator;
use Contena\Frontend\Theme\Aggregate\ThemeChannelDefinition;
use Contena\Tests\Unit\Frontend\Theme\MockedCacheInvalidator;

/**
 * @internal
 */
#[CoversClass(CachedDomainLoaderInvalidator::class)]
class CachedDomainLoaderInvalidatorTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [EntityWrittenContainerEvent::class => [['invalidate', 2000]]],
            CachedDomainLoaderInvalidator::getSubscribedEvents()
        );
    }

    public function testInvalidateIsCalledForChannelWrittenEvent(): void
    {
        $context = Context::createDefaultContext();

        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(ChannelDefinition::ENTITY_NAME, [], $context)]),
            []
        );

        $mockedInvalidator = new MockedCacheInvalidator();

        $invalidationSubscriber = new CachedDomainLoaderInvalidator(
            $mockedInvalidator
        );

        $invalidationSubscriber->invalidate($event);

        static::assertSame(
            [CachedDomainLoader::DOMAIN_COLLECTION_CACHE_KEY],
            $mockedInvalidator->getForceInvalidatedTags()
        );
    }

    public function testInvalidateIsNotCalledForNonChannelWrites(): void
    {
        $context = Context::createDefaultContext();

        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(BlogDefinition::ENTITY_NAME, [], $context)]),
            []
        );

        $mockedInvalidator = new MockedCacheInvalidator();

        $invalidationSubscriber = new CachedDomainLoaderInvalidator(
            $mockedInvalidator
        );

        $invalidationSubscriber->invalidate($event);

        static::assertSame([], $mockedInvalidator->getForceInvalidatedTags());
    }

    public function testInvalidateIsCalledForThemeChannelWrittenEvent(): void
    {
        $context = Context::createDefaultContext();

        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(ThemeChannelDefinition::ENTITY_NAME, [], $context)]),
            []
        );

        $mockedInvalidator = new MockedCacheInvalidator();

        $invalidationSubscriber = new CachedDomainLoaderInvalidator(
            $mockedInvalidator
        );

        $invalidationSubscriber->invalidate($event);

        static::assertSame(
            [CachedDomainLoader::DOMAIN_COLLECTION_CACHE_KEY],
            $mockedInvalidator->getForceInvalidatedTags()
        );
    }
}
