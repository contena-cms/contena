<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\RateLimit;

use Contena\Core\Framework\Mcp\McpException;
use Contena\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Wraps the core rate limiter for the MCP endpoints. The throttle handling is
 * shared, while the rate-limit key is derived from the OAuth access token with
 * a stable per-IP backstop.
 */
class McpRateLimiter
{
    /**
     * @internal
     */
    public function __construct(private readonly RateLimiter $rateLimiter)
    {
    }

    public function enforceForAdminApi(Request $request): void
    {
        $key = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID)
            ?: $request->getClientIp()
            ?: 'unknown';

        $this->enforce(RateLimiter::MCP_ADMIN_API, $key);
    }

    public function enforceForChannelApi(Request $request): void
    {
        $channelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

        if ($channelContext instanceof ChannelContext) {
            $this->enforce(RateLimiter::MCP_CHANNEL_API, $channelContext->getChannelId() . '-' . $channelContext->getToken());
        }

        $this->enforce(RateLimiter::MCP_CHANNEL_API, $request->getClientIp() ?: 'unknown');
    }

    private function enforce(string $route, string $key): void
    {
        try {
            $this->rateLimiter->ensureAccepted($route, $key);
        } catch (RateLimitExceededException $e) {
            throw McpException::throttled($e->getWaitTime(), $e);
        }
    }
}
