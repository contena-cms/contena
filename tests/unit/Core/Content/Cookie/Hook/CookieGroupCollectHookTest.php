<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cookie\Hook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cookie\Hook\CookieGroupCollectHook;
use Contena\Core\Content\Cookie\Struct\CookieGroupCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Facade\ChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * @internal
 */
#[CoversClass(CookieGroupCollectHook::class)]
class CookieGroupCollectHookTest extends TestCase
{
    public function testHook(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $context = Context::createDefaultContext();
        $channelContext->method('getContext')->willReturn($context);

        $cookieGroups = new CookieGroupCollection();

        $hook = new CookieGroupCollectHook($cookieGroups, $channelContext);

        static::assertSame('cookie-group-collect', $hook->getName());
        static::assertSame($cookieGroups, $hook->getCookieGroups());
        static::assertSame($channelContext, $hook->getChannelContext());
        static::assertSame($context, $hook->getContext());
    }

    public function testGetServiceIds(): void
    {
        static::assertSame(
            [
                RepositoryFacadeHookFactory::class,
                SystemConfigFacadeHookFactory::class,
                ChannelRepositoryFacadeHookFactory::class,
            ],
            CookieGroupCollectHook::getServiceIds()
        );
    }
}
