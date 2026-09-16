<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Routing\Facade;

use Contena\Core\Framework\Routing\Facade\RequestFacade;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RequestFacade::class)]
class RequestFacadeTest extends TestCase
{
    public function testUrl(): void
    {
        $request = new Request();
        $request->server->set('REQUEST_URI', '/foo/bar');
        $request->attributes->set('ct-original-request-uri', 'https://example.com/foo/bar');

        static::assertSame('https://example.com/foo/bar', new RequestFacade($request)->uri());
    }

    public function testUrlOutsideFrontend(): void
    {
        $request = new Request();
        $request->server->set('REQUEST_URI', '/foo/bar');

        static::assertSame('/foo/bar', new RequestFacade($request)->uri());
    }

    public function testPathInfo(): void
    {
        $request = new Request();
        $request->server->set('REQUEST_URI', '/foo/bar');

        static::assertSame('/foo/bar', new RequestFacade($request)->pathInfo());
    }

    public function testScheme(): void
    {
        static::assertSame('http', new RequestFacade(new Request())->scheme());
    }

    public function testMethod(): void
    {
        $request = new Request();
        $request->setMethod('POST');

        static::assertSame('POST', new RequestFacade($request)->method());
    }

    public function testQuery(): void
    {
        $request = new Request();
        $request->query->set('foo', 'bar');

        static::assertSame(['foo' => 'bar'], new RequestFacade($request)->query());
    }

    public function testRequest(): void
    {
        $request = new Request();
        $request->request->set('foo', 'bar');

        static::assertSame(['foo' => 'bar'], new RequestFacade($request)->request());
    }

    public function testHeaders(): void
    {
        $request = new Request();
        $request->headers->set('foo', 'bar');
        $request->headers->set('accept', 'application/json');

        static::assertSame(['accept' => ['application/json']], new RequestFacade($request)->headers());
    }

    public function testCookies(): void
    {
        $request = new Request();
        $request->cookies->set('foo', 'bar');

        static::assertSame(['foo' => 'bar'], new RequestFacade($request)->cookies());
    }

    public function testIp(): void
    {
        $request = new Request();

        static::assertSame($request->getClientIp(), new RequestFacade($request)->ip());
    }
}
