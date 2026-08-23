<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Controller;

use Mcp\Server;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\Controller\ChannelApiMcpServerController;
use Contena\Core\Framework\Mcp\Http\McpHttpTransportFactory;
use Contena\Core\Framework\Mcp\McpAllowedHostsProvider;
use Contena\Core\Framework\Mcp\McpException;
use Contena\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Contena\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Contena\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Contena\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Contena\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Contena\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ChannelApiMcpServerController::class)]
class ChannelApiMcpServerControllerTest extends TestCase
{
    private RateLimiter&MockObject $rateLimiter;

    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->psr17 = new Psr17Factory();
    }

    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
    }

    public function testHandleReturnsResponseForValidChannelApiMcpRequest(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR);

        $this->rateLimiter->expects($this->atLeastOnce())->method('ensureAccepted');

        $psrRequest = new ServerRequest('POST', '/channel-api/_mcp', ['Content-Type' => 'application/json'], $body);
        $controller = $this->buildController($psrRequest, new HttpFoundationFactory());

        $sfRequest = Request::create('/channel-api/_mcp', 'POST', content: $body);
        $sfRequest->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $this->createChannelContext());

        $response = $controller->handle($sfRequest);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testRateLimitUsesChannelContextAndClientIp(): void
    {
        $channelContext = $this->createChannelContext();

        // The context token is rotatable, so the endpoint is throttled on both the per-context key
        // and a stable per-IP key (same mcp_channel_api bucket).
        $calls = [];
        $this->rateLimiter
            ->expects($this->exactly(2))
            ->method('ensureAccepted')
            ->willReturnCallback(static function (string $route, string $key) use (&$calls): void {
                $calls[] = [$route, $key];
            });

        $controller = $this->buildController(
            new ServerRequest('GET', '/channel-api/_mcp'),
            static::createStub(HttpFoundationFactoryInterface::class),
        );

        $sfRequest = Request::create('/channel-api/_mcp', 'GET');
        $sfRequest->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContext);

        $controller->handle($sfRequest);

        static::assertSame([
            [RateLimiter::MCP_CHANNEL_API, 'channel-id-context-token'],
            [RateLimiter::MCP_CHANNEL_API, '127.0.0.1'],
        ], $calls);
    }

    public function testRateLimitExceptionIsConvertedToMcpException(): void
    {
        Clock::set(new MockClock('2026-01-01 00:00:00'));
        $rateLimitException = new RateLimitExceededException(new \DateTimeImmutable('2026-01-01 00:01:00')->getTimestamp());

        $this->rateLimiter
            ->expects($this->once())
            ->method('ensureAccepted')
            ->willThrowException($rateLimitException);

        $controller = $this->buildController(new ServerRequest('GET', '/channel-api/_mcp'));

        $this->expectExceptionObject(McpException::throttled(60, $rateLimitException));

        $controller->handle(new Request());
    }

    public function testMalformedSessionIdHeaderIsRejected(): void
    {
        $this->rateLimiter
            ->expects($this->never())
            ->method('ensureAccepted');

        $controller = $this->buildController(new ServerRequest('POST', '/channel-api/_mcp'));

        $request = Request::create('/channel-api/_mcp', 'POST');
        $request->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, 'not-a-uuid');

        $this->expectExceptionObject(McpException::invalidSessionId());

        $controller->handle($request);
    }

    public function testInitializeRegistersMcpSession(): void
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
            'id' => 1,
        ], \JSON_THROW_ON_ERROR);

        $this->rateLimiter->expects($this->atLeastOnce())->method('ensureAccepted');

        $sessionRegistry = $this->createMock(McpSessionRegistry::class);
        $sessionRegistry->expects($this->once())
            ->method('register')
            ->with(static::callback(static fn (string $sessionId): bool => $sessionId !== ''));

        $psrRequest = new ServerRequest('POST', '/channel-api/_mcp', ['Content-Type' => 'application/json'], $body);
        $controller = $this->buildController(
            $psrRequest,
            new HttpFoundationFactory(),
            sessionRegistry: $sessionRegistry,
        );

        $sfRequest = Request::create('/channel-api/_mcp', 'POST', content: $body);
        $sfRequest->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $this->createChannelContext());

        $response = $controller->handle($sfRequest);

        static::assertNotSame('', (string) $response->headers->get(PlatformRequest::HEADER_MCP_SESSION_ID));
    }

    public function testDoesNotRegisterSessionWhenResponseHasNoSessionHeader(): void
    {
        $this->rateLimiter->expects($this->atLeastOnce())->method('ensureAccepted');

        $sessionRegistry = $this->createMock(McpSessionRegistry::class);
        $sessionRegistry->expects($this->never())->method('register');

        $httpFoundationFactory = static::createStub(HttpFoundationFactoryInterface::class);
        $httpFoundationFactory->method('createResponse')->willReturn(new Response('', 405));

        $controller = $this->buildController(
            new ServerRequest('GET', '/channel-api/_mcp'),
            $httpFoundationFactory,
            sessionRegistry: $sessionRegistry,
        );

        $sfRequest = Request::create('/channel-api/_mcp', 'GET');
        $sfRequest->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $this->createChannelContext());

        $response = $controller->handle($sfRequest);

        static::assertSame(405, $response->getStatusCode());
    }

    public function testFlushesPendingToolsListChangedForActiveSession(): void
    {
        $this->rateLimiter->expects($this->atLeastOnce())->method('ensureAccepted');

        $sessionId = Uuid::v4()->toRfc4122();

        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->once())
            ->method('notifySession')
            ->with(
                $sessionId,
                static::callback(static fn (McpListChangedNotificationSet $set): bool => $set->tools && !$set->resources && !$set->prompts),
            );

        $controller = $this->buildController(
            new ServerRequest('POST', '/channel-api/_mcp'),
            new HttpFoundationFactory(),
            listChangedNotifier: $notifier,
        );

        $request = Request::create('/channel-api/_mcp', 'POST');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $this->createChannelContext());
        $request->attributes->set(McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE, true);
        $request->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, $sessionId);

        $controller->handle($request);
    }

    public function testDoesNotFlushWhenNoPendingNotification(): void
    {
        $this->rateLimiter->expects($this->atLeastOnce())->method('ensureAccepted');

        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->never())->method('notifySession');

        $controller = $this->buildController(
            new ServerRequest('POST', '/channel-api/_mcp'),
            new HttpFoundationFactory(),
            listChangedNotifier: $notifier,
        );

        $request = Request::create('/channel-api/_mcp', 'POST');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $this->createChannelContext());
        $request->headers->set(PlatformRequest::HEADER_MCP_SESSION_ID, Uuid::v4()->toRfc4122());

        $controller->handle($request);
    }

    public function testDoesNotFlushWhenSessionHeaderMissing(): void
    {
        $this->rateLimiter->expects($this->atLeastOnce())->method('ensureAccepted');

        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->never())->method('notifySession');

        $controller = $this->buildController(
            new ServerRequest('POST', '/channel-api/_mcp'),
            new HttpFoundationFactory(),
            listChangedNotifier: $notifier,
        );

        $request = Request::create('/channel-api/_mcp', 'POST');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $this->createChannelContext());
        $request->attributes->set(McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE, true);

        $controller->handle($request);
    }

    private function buildController(
        ServerRequest $psrRequest,
        ?HttpFoundationFactoryInterface $httpFoundationFactory = null,
        ?Server $server = null,
        ?McpSessionRegistry $sessionRegistry = null,
        ?McpListChangedNotifier $listChangedNotifier = null,
    ): ChannelApiMcpServerController {
        $httpMessageFactory = static::createStub(HttpMessageFactoryInterface::class);
        $httpMessageFactory->method('createRequest')->willReturn($psrRequest);

        $transportFactory = new McpHttpTransportFactory(
            $httpMessageFactory,
            $this->psr17,
            $this->psr17,
            $httpFoundationFactory ?? static::createStub(HttpFoundationFactoryInterface::class),
            static::createStub(McpAllowedHostsProvider::class),
        );

        return new ChannelApiMcpServerController(
            $server ?? Server::builder()->build(),
            $transportFactory,
            new McpRateLimiter($this->rateLimiter),
            new McpSessionIdValidator(),
            sessionRegistry: $sessionRegistry,
            listChangedNotifier: $listChangedNotifier,
        );
    }

    private function createChannelContext(): ChannelContext&Stub
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn('channel-id');
        $channelContext->method('getToken')->willReturn('context-token');

        return $channelContext;
    }
}
