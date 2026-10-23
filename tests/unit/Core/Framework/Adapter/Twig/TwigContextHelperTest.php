<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Twig\TwigContextHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(TwigContextHelper::class)]
class TwigContextHelperTest extends TestCase
{
    public function testGetContextReturnsInjectedCoreContext(): void
    {
        $context = Context::createDefaultContext();
        $channelContext = Generator::generateChannelContext();

        static::assertSame($context, TwigContextHelper::getContext([
            'context' => $context,
            'channelContext' => $channelContext,
        ]));
    }

    public function testGetContextReturnsCoreContextFromChannelContext(): void
    {
        $channelContext = Generator::generateChannelContext();

        static::assertSame($channelContext->getContext(), TwigContextHelper::getContext([
            'context' => $channelContext,
        ]));
    }

    public function testGetContextReturnsCoreContextFromChannelContextFallback(): void
    {
        $channelContext = Generator::generateChannelContext();

        static::assertSame($channelContext->getContext(), TwigContextHelper::getContext([
            'channelContext' => $channelContext,
        ]));
    }

    public function testGetChannelContextReturnsContextVariable(): void
    {
        $channelContext = Generator::generateChannelContext();

        static::assertSame($channelContext, TwigContextHelper::getChannelContext([
            'context' => $channelContext,
        ]));
    }

    public function testGetChannelContextReturnsFallbackVariable(): void
    {
        $channelContext = Generator::generateChannelContext();

        static::assertSame($channelContext, TwigContextHelper::getChannelContext([
            'context' => Context::createDefaultContext(),
            'channelContext' => $channelContext,
        ]));
    }

    public function testReturnsNullWithoutContext(): void
    {
        static::assertNull(TwigContextHelper::getContext([]));
        static::assertNull(TwigContextHelper::getChannelContext([]));
    }
}
