<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelDefinition;
use Contena\Storefront\Framework\Routing\CachedDomainLoader;
use Contena\Storefront\Framework\Routing\CachedDomainLoaderInvalidator;
use Contena\Storefront\Theme\Aggregate\ThemeSalesChannelDefinition;

/**
 * @internal
 */
#[Package('discovery')]
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

    public function testInvalidateIsCalledForSalesChannelWrittenEvent(): void
    {
        $context = Context::createDefaultContext();

        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(SalesChannelDefinition::ENTITY_NAME, [], $context)]),
            []
        );

        $mockedInvalidator = new MockedCacheInvalidator();

        $invalidationSubscriber = new CachedDomainLoaderInvalidator(
            $mockedInvalidator
        );

        $invalidationSubscriber->invalidate($event);

        static::assertSame(
            [CachedDomainLoader::CACHE_KEY, CachedDomainLoader::DOMAIN_COLLECTION_CACHE_KEY],
            $mockedInvalidator->getForceInvalidatedTags()
        );
    }

    public function testInvalidateIsCalledForThemeSalesChannelWrittenEvent(): void
    {
        $context = Context::createDefaultContext();

        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(ThemeSalesChannelDefinition::ENTITY_NAME, [], $context)]),
            []
        );

        $mockedInvalidator = new MockedCacheInvalidator();

        $invalidationSubscriber = new CachedDomainLoaderInvalidator(
            $mockedInvalidator
        );

        $invalidationSubscriber->invalidate($event);

        static::assertSame(
            [CachedDomainLoader::CACHE_KEY, CachedDomainLoader::DOMAIN_COLLECTION_CACHE_KEY],
            $mockedInvalidator->getForceInvalidatedTags()
        );
    }

    public function testInvalidateIsNotCalledForNonSalesChannelWrites(): void
    {
        $context = Context::createDefaultContext();

        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(ProductDefinition::ENTITY_NAME, [], $context)]),
            []
        );

        $mockedInvalidator = new MockedCacheInvalidator();

        $invalidationSubscriber = new CachedDomainLoaderInvalidator(
            $mockedInvalidator
        );

        $invalidationSubscriber->invalidate($event);

        static::assertSame([], $mockedInvalidator->getForceInvalidatedTags());
    }
}
