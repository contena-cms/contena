<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\SystemCheck\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Contena\Core\ChannelRequest;
use Contena\Core\Framework\SystemCheck\Check\Result;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainUtil;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(ChannelDomainUtil::class)]
class ChannelDomainUtilTest extends TestCase
{
    private KernelInterface&Stub $kernel;

    private RouterInterface&Stub $router;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->kernel = static::createStub(KernelInterface::class);
        $this->router = static::createStub(RouterInterface::class);
        $this->requestStack = new RequestStack();
    }

    public function testRunAsChannelRequest(): void
    {
        $this->requestStack->push(new Request([], [], [
            ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
        ]));

        $util = $this->getUtil();

        $result = $util->runAsChannelRequest(static function () {
            return new Result(
                'test',
                Status::OK,
                'Test completed successfully'
            );
        });

        static::assertSame('test', $result->name);
        static::assertSame(Status::OK, $result->status);

        $request = $this->requestStack->getMainRequest();
        static::assertInstanceOf(Request::class, $request);
        static::assertTrue($request->attributes->get(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST, false));
    }

    public function testRunAsChannelRequestWithoutMainRequest(): void
    {
        $util = $this->getUtil();

        $result = $util->runAsChannelRequest(static function () {
            return new Result(
                'test',
                Status::OK,
                'Test completed successfully'
            );
        });

        static::assertSame('test', $result->name);
        static::assertSame(Status::OK, $result->status);
        static::assertEmpty($this->requestStack->getMainRequest());
    }

    public function testRunWhileTrustingAllHosts(): void
    {
        Request::setTrustedHosts(['example.com']);

        $util = $this->getUtil();

        $result = $util->runWhileTrustingAllHosts(static function () {
            static::assertSame([], Request::getTrustedHosts());

            return new Result(
                'test',
                Status::OK,
                'Test completed successfully'
            );
        });

        static::assertSame('test', $result->name);
        static::assertSame(Status::OK, $result->status);

        static::assertSame(['{example.com}i'], Request::getTrustedHosts());

        Request::setTrustedHosts([]);
    }

    public function testGenerateDomainUrl(): void
    {
        $url = 'https://example.com';
        $routeName = 'test_route';
        $parameters = ['param1' => 'value1', 'param2' => 'value2'];

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with($routeName, $parameters)
            ->willReturn('/test/path');

        $util = $this->getUtil($router);

        $resultUrl = $util->generateDomainUrl($url, $routeName, $parameters);

        static::assertSame('https://example.com/test/path', $resultUrl);
    }

    public function testCreateEmptyResult(): void
    {
        $util = $this->getUtil();

        $result = $util->createEmptyResult('test', 'This is a test message');

        static::assertSame('test', $result->name);
        static::assertSame(Status::SKIPPED, $result->status);
        static::assertSame('This is a test message', $result->message);
        static::assertTrue($result->healthy);
    }

    public function testHandleRequestWithRedirects(): void
    {
        $this->kernel->method('handle')->willReturnOnConsecutiveCalls(
            new RedirectResponse('http://localhost/seo', Response::HTTP_MOVED_PERMANENTLY),
            new RedirectResponse('http://localhost/blog/123', Response::HTTP_MOVED_PERMANENTLY),
            new Response(status: Response::HTTP_OK),
        );

        $util = $this->getUtil();
        $request = new Request();

        $result = $util->handleRequest($request);
        static::assertSame('http://localhost/blog/123', $result->frontendUrl);
        static::assertSame(Response::HTTP_OK, $result->responseCode);
    }

    public function testHandleRequestsDetectsLoop(): void
    {
        $this->kernel->method('handle')->willReturnOnConsecutiveCalls(
            ...array_fill(0, 6, new RedirectResponse('http://localhost/blog/123', Response::HTTP_MOVED_PERMANENTLY)),
        );

        $util = $this->getUtil();
        $request = new Request();

        $result = $util->handleRequest($request);
        static::assertSame('http://localhost/blog/123', $result->frontendUrl);
        static::assertSame(Response::HTTP_LOOP_DETECTED, $result->responseCode);
    }

    private function getUtil(?RouterInterface $router = null): ChannelDomainUtil
    {
        return new ChannelDomainUtil(
            $router ?? $this->router,
            $this->requestStack,
            $this->kernel,
            new NullLogger(),
            new NativeClock()
        );
    }
}
