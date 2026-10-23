<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing\NotFound;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\NotFound\NotFoundPageCacheKeyEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(NotFoundPageCacheKeyEvent::class)]
class NotFoundPageCacheKeyEventTest extends TestCase
{
    public function testEvent(): void
    {
        $request = new Request();
        $context = static::createStub(ChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $event = new NotFoundPageCacheKeyEvent('test', $request, $context);

        static::assertSame('test', $event->getKey());
        static::assertSame($context->getContext(), $event->getContext());
        static::assertSame($context, $event->getChannelContext());
        static::assertSame($request, $event->getRequest());

        $event->setKey('test2');
        static::assertSame('test2', $event->getKey());
    }
}
