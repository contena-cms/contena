<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(McpContextProvider::class)]
class McpContextProviderTest extends TestCase
{
    public function testReturnsContextFromRequest(): void
    {
        $context = Context::createDefaultContext();

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = new McpContextProvider($requestStack);

        static::assertSame($context, $provider->getContext());
    }

    public function testReturnsCLIContextWhenNoRequest(): void
    {
        $requestStack = new RequestStack();

        $provider = new McpContextProvider($requestStack);
        $context = $provider->getContext();

        static::assertSame(Context::createCLIContext()->getSource()::class, $context->getSource()::class);
    }

    public function testReturnsCLIContextWhenNoContextOnRequest(): void
    {
        $request = new Request();

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = new McpContextProvider($requestStack);
        $context = $provider->getContext();

        static::assertSame(Context::createCLIContext()->getSource()::class, $context->getSource()::class);
    }
}
