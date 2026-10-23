<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Hook\Pricing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Hook\Pricing\BlogPricingHook;
use Contena\Core\Content\Blog\Hook\Pricing\BlogProxy;
use Contena\Core\Framework\DataAbstractionLayer\Facade\ChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * @internal
 */
#[CoversClass(BlogPricingHook::class)]
class BlogPricingHookTest extends TestCase
{
    public function testGetBlogs(): void
    {
        $channelContext = static::createStub(ChannelContext::class);

        $blogProxy = new BlogProxy(
            (new ChannelBlogEntity())->assign(['name' => 'foo']),
            $channelContext,
            static::createStub(ScriptPriceStubs::class)
        );
        $blogPricingHook = new BlogPricingHook([$blogProxy], $channelContext);

        static::assertSame([$blogProxy], $blogPricingHook->getBlogs());
    }

    public function testGetServiceIds(): void
    {
        static::assertSame(
            [
                RepositoryFacadeHookFactory::class,
                SystemConfigFacadeHookFactory::class,
                ChannelRepositoryFacadeHookFactory::class,
            ],
            BlogPricingHook::getServiceIds()
        );
    }

    public function testGetName(): void
    {
        $blogPricingHook = new BlogPricingHook([], static::createStub(ChannelContext::class));

        static::assertSame('blog-pricing', $blogPricingHook->getName());
    }

    public function testGetChannelContext(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $blogPricingHook = new BlogPricingHook([], $channelContext);

        static::assertSame($channelContext, $blogPricingHook->getChannelContext());
    }
}
