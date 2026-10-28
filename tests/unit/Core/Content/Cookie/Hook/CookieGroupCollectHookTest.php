<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cookie\Hook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cookie\Hook\CookieGroupCollectHook;
use Contena\Core\Content\Cookie\Struct\CookieGroupCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CookieGroupCollectHook::class)]
class CookieGroupCollectHookTest extends TestCase
{
    public function testHook(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $context = Context::createDefaultContext();
        $salesChannelContext->method('getContext')->willReturn($context);

        $cookieGroups = new CookieGroupCollection();

        $hook = new CookieGroupCollectHook($cookieGroups, $salesChannelContext);

        static::assertSame('cookie-group-collect', $hook->getName());
        static::assertSame($cookieGroups, $hook->getCookieGroups());
        static::assertSame($salesChannelContext, $hook->getSalesChannelContext());
        static::assertSame($context, $hook->getContext());
    }

    public function testGetServiceIds(): void
    {
        static::assertSame(
            [
                RepositoryFacadeHookFactory::class,
                SystemConfigFacadeHookFactory::class,
                SalesChannelRepositoryFacadeHookFactory::class,
            ],
            CookieGroupCollectHook::getServiceIds()
        );
    }
}
