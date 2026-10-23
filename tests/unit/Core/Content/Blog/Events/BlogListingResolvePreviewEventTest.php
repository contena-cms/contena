<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Events\BlogListingResolvePreviewEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(BlogListingResolvePreviewEvent::class)]
class BlogListingResolvePreviewEventTest extends TestCase
{
    public function testReplace(): void
    {
        $event = new BlogListingResolvePreviewEvent(
            static::createStub(ChannelContext::class),
            new Criteria(),
            ['p1' => 'p1'],
            true
        );

        $event->replace('p1', 'p2');
        static::assertSame(['p1' => 'p2'], $event->getMapping());
    }

    public function testReplaceException(): void
    {
        $event = new BlogListingResolvePreviewEvent(
            static::createStub(ChannelContext::class),
            new Criteria(),
            ['p1' => 'p1'],
            true
        );

        static::expectException(\RuntimeException::class);
        $event->replace('p3', 'p2');
    }

    public function testExposesItsPayload(): void
    {
        $context = Context::createDefaultContext();
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getContext')->willReturn($context);
        $criteria = new Criteria();

        $event = new BlogListingResolvePreviewEvent($channelContext, $criteria, ['p1' => 'p1'], true);

        static::assertSame($criteria, $event->getCriteria());
        static::assertSame($channelContext, $event->getChannelContext());
        static::assertSame($context, $event->getContext());
        static::assertTrue($event->hasOptionFilter());
    }
}
