<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\Events\ProductListingCollectSortingEvent;
use Contena\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductListingCollectSortingEvent::class)]
class ProductListingCollectSortingEventTest extends TestCase
{
    public function testExposesItsPayload(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        $request = new Request();
        $sortings = new ProductSortingCollection();

        $event = new ProductListingCollectSortingEvent($request, $sortings, $salesChannelContext);

        static::assertSame($request, $event->getRequest());
        static::assertSame($sortings, $event->getSortings());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($context, $event->getContext());
    }
}
