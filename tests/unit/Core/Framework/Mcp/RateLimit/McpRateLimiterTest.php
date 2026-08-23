<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\RateLimit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\McpException;
use Contena\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Contena\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(McpRateLimiter::class)]
class McpRateLimiterTest extends TestCase
{
    private RateLimiter&MockObject $rateLimiter;

    protected function setUp(): void
    {
        $this->rateLimiter = $this->createMock(RateLimiter::class);
    }

    /**
     * @return iterable<string, array{?string, ?string, string}>
     */
    public static function keyProvider(): iterable
    {
        yield 'OAuth token has priority' => [null, 'token-id', 'token-id'];
        yield 'client IP is the fallback' => ['192.0.2.1', null, '192.0.2.1'];
        yield 'unknown is the final fallback' => [null, null, 'unknown'];
    }

    #[DataProvider('keyProvider')]
    public function testUsesStablePrincipalKey(?string $clientIp, ?string $tokenId, string $expected): void
    {
        $request = new Request(server: $clientIp === null ? [] : ['REMOTE_ADDR' => $clientIp]);
        if ($tokenId !== null) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID, $tokenId);
        }
        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::MCP_ADMIN_API, $expected);

        new McpRateLimiter($this->rateLimiter)->enforceForAdminApi($request);
    }

    public function testTranslatesRateLimitException(): void
    {
        $exception = new RateLimitExceededException(new \DateTimeImmutable('+60 seconds')->getTimestamp());
        $this->rateLimiter->expects($this->once())->method('ensureAccepted')->willThrowException($exception);
        $this->expectExceptionObject(McpException::throttled($exception->getWaitTime(), $exception));

        new McpRateLimiter($this->rateLimiter)->enforceForAdminApi(new Request());
    }

    public function testEnforceForChannelApiUsesContextAndClientIpBuckets(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn('channel-id');
        $channelContext->method('getToken')->willReturn('context-token');

        $request = new Request(server: ['REMOTE_ADDR' => '192.0.2.1']);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContext);

        $calls = [];
        $this->rateLimiter->expects($this->exactly(2))
            ->method('ensureAccepted')
            ->willReturnCallback(static function (string $route, string $key) use (&$calls): void {
                $calls[] = [$route, $key];
            });

        new McpRateLimiter($this->rateLimiter)->enforceForChannelApi($request);

        static::assertSame([
            [RateLimiter::MCP_CHANNEL_API, 'channel-id-context-token'],
            [RateLimiter::MCP_CHANNEL_API, '192.0.2.1'],
        ], $calls);
    }

    public function testEnforceForChannelApiTranslatesRateLimitException(): void
    {
        $exception = new RateLimitExceededException(new \DateTimeImmutable('+60 seconds')->getTimestamp());
        $this->rateLimiter->expects($this->once())->method('ensureAccepted')->willThrowException($exception);

        $this->expectExceptionObject(McpException::throttled($exception->getWaitTime(), $exception));

        new McpRateLimiter($this->rateLimiter)->enforceForChannelApi(new Request());
    }
}
