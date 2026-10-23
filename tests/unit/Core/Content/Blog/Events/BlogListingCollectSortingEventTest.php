<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Events\BlogListingCollectSortingEvent;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogListingCollectSortingEvent::class)]
class BlogListingCollectSortingEventTest extends TestCase
{
    public function testExposesItsPayload(): void
    {
        $context = Context::createDefaultContext();
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getContext')->willReturn($context);

        $request = new Request();
        $sortings = new BlogSortingCollection();

        $event = new BlogListingCollectSortingEvent($request, $sortings, $channelContext);

        static::assertSame($request, $event->getRequest());
        static::assertSame($sortings, $event->getSortings());
        static::assertSame($channelContext, $event->getChannelContext());
        static::assertSame($context, $event->getContext());
    }
}
