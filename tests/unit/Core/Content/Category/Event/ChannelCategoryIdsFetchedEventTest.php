<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\Event\ChannelCategoryIdsFetchedEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(ChannelCategoryIdsFetchedEvent::class)]
class ChannelCategoryIdsFetchedEventTest extends TestCase
{
    public function testDeduplicatesTheFetchedIds(): void
    {
        $event = new ChannelCategoryIdsFetchedEvent(
            ['id-a', 'id-b', 'id-a'],
            static::createStub(ChannelContext::class),
        );

        static::assertSame(['id-a', 'id-b'], $event->getIds());
    }

    public function testFilterIdRemovesTheGivenId(): void
    {
        $event = new ChannelCategoryIdsFetchedEvent(
            ['id-a', 'id-b'],
            static::createStub(ChannelContext::class),
        );

        static::assertTrue($event->hasId('id-a'));

        $event->filterId('id-a');

        static::assertFalse($event->hasId('id-a'));
        static::assertSame(['id-b'], $event->getIds());
    }

    public function testExposesTheChannelContext(): void
    {
        $context = Context::createDefaultContext();
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getContext')->willReturn($context);

        $event = new ChannelCategoryIdsFetchedEvent(['id-a'], $channelContext);

        static::assertSame($channelContext, $event->getChannelContext());
        static::assertSame($context, $event->getContext());
    }
}
