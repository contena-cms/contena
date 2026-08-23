<?php declare(strict_types=1);

namespace Contena\Core\Framework\RateLimiter;

use Contena\Core\Framework\Context;

class RateLimiter
{
    final public const string LOGIN_ROUTE = 'login';

    final public const string LOGIN_USER = 'login_user';

    final public const string LOGIN_CLIENT = 'login_client';

    final public const string RESET_PASSWORD = 'reset_password';

    final public const string OAUTH = 'oauth';

    final public const string OAUTH_USER = 'oauth_user';

    final public const string OAUTH_CLIENT = 'oauth_client';

    final public const string USER_RECOVERY = 'user_recovery';

    final public const string MCP_ADMIN_API = 'mcp_admin_api';

    final public const string MCP_CHANNEL_API = 'mcp_channel_api';

    /**
     * @var array<string, RateLimiterFactory>
     */
    private array $factories;

    public function reset(string $route, string $key, ?Context $context = null): void
    {
        $this->getFactory($route)->create($this->scopeKey($key, $context))->reset();
    }

    public function resetIfConfigured(string $route, string $key, ?Context $context = null): void
    {
        $factory = $this->factories[$route] ?? null;

        $factory?->create($this->scopeKey($key, $context))->reset();
    }

    public function ensureAccepted(string $route, string $key, ?Context $context = null): void
    {
        $limiter = $this->getFactory($route)->create($this->scopeKey($key, $context))->consume();

        if (!$limiter->isAccepted()) {
            throw RateLimiterException::limitExceeded($limiter->getRetryAfter()->getTimestamp());
        }
    }

    public function ensureAcceptedIfConfigured(string $route, string $key, ?Context $context = null): void
    {
        $factory = $this->factories[$route] ?? null;

        if ($factory === null) {
            return;
        }

        $limiter = $factory->create($this->scopeKey($key, $context))->consume();

        if (!$limiter->isAccepted()) {
            throw RateLimiterException::limitExceeded($limiter->getRetryAfter()->getTimestamp());
        }
    }

    public function registerLimiterFactory(string $route, RateLimiterFactory $factory): void
    {
        $this->factories[$route] = $factory;
    }

    private function getFactory(string $route): RateLimiterFactory
    {
        $factory = $this->factories[$route] ?? null;

        if ($factory === null) {
            throw RateLimiterException::factoryNotFound($route);
        }

        return $factory;
    }

    private function scopeKey(string $key, ?Context $context): string
    {
        $tenantId = $context?->getTenantId();

        return $tenantId === null ? $key : \sprintf('tenant:%s:%s', $tenantId, $key);
    }
}
