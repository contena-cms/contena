<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Event\ChannelContextCreatedEvent;

/**
 * @internal
 */
#[CoversClass(ChannelContextCreatedEvent::class)]
class ChannelContextCreatedEventTest extends TestCase
{
    public function testEventReturnsAllNeededData(): void
    {
        $token = 'foo';
        $context = Context::createDefaultContext();
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getContext')->willReturn($context);

        $event = new ChannelContextCreatedEvent($channelContext, $token);
        static::assertSame($token, $event->getUsedToken());
        static::assertSame($context, $event->getContext());
        static::assertSame($channelContext, $event->getChannelContext());
    }
}
