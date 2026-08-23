<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Twig\TwigContextHelper;
use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(TwigContextHelper::class)]
class TwigContextHelperTest extends TestCase
{
    public function testGetsCoreContextDirectly(): void
    {
        $context = Context::createDefaultContext();

        static::assertSame($context, TwigContextHelper::getContext(['context' => $context]));
    }

    public function testGetsCoreContextFromChannelContext(): void
    {
        $context = Context::createDefaultContext();
        $channel = new ChannelEntity();
        $channel->setId('channel-1');
        $channelContext = Generator::generateChannelContext(baseContext: $context, token: 'token', channel: $channel);

        static::assertSame($context, TwigContextHelper::getContext(['context' => $channelContext]));
    }

    public function testGetsChannelContextFromFallbackVariable(): void
    {
        $channel = new ChannelEntity();
        $channel->setId('channel-1');
        $channelContext = Generator::generateChannelContext(token: 'token', channel: $channel);

        static::assertSame($channelContext, TwigContextHelper::getChannelContext(['channelContext' => $channelContext]));
    }

    public function testReturnsNullWithoutContext(): void
    {
        static::assertNull(TwigContextHelper::getContext([]));
        static::assertNull(TwigContextHelper::getChannelContext([]));
    }
}
