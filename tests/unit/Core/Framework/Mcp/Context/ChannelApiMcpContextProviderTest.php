<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Mcp\Context\ChannelApiMcpContextProvider;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(ChannelApiMcpContextProvider::class)]
class ChannelApiMcpContextProviderTest extends TestCase
{
    public function testReturnsChannelContextFromRequest(): void
    {
        $channelContext = static::createStub(ChannelContext::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContext);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = new ChannelApiMcpContextProvider($requestStack);

        static::assertSame($channelContext, $provider->getChannelContext());
    }

    public function testReturnsContextFromChannelContext(): void
    {
        $context = Context::createDefaultContext();
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getContext')->willReturn($context);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContext);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = new ChannelApiMcpContextProvider($requestStack);

        static::assertSame($context, $provider->getContext());
    }

    public function testReturnsNullWhenNoChannelContextExists(): void
    {
        $provider = new ChannelApiMcpContextProvider(new RequestStack());

        static::assertNull($provider->getChannelContext());
        static::assertSame(Context::createCLIContext()->getSource()::class, $provider->getContext()->getSource()::class);
    }
}
