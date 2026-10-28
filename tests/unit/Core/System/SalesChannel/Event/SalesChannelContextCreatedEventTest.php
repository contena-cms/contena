<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SalesChannel\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelContextCreatedEvent::class)]
class SalesChannelContextCreatedEventTest extends TestCase
{
    public function testEventReturnsAllNeededData(): void
    {
        $token = 'foo';
        $context = Context::createDefaultContext();
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        $event = new SalesChannelContextCreatedEvent($salesChannelContext, $token);
        static::assertSame($token, $event->getUsedToken());
        static::assertSame($context, $event->getContext());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
    }
}
