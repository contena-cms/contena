<?php
declare(strict_types=1);

namespace Contena\Core\Framework\RateLimiter;

use Contena\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\Policy\NoLimiter;

class NoLimitRateLimiterFactory extends RateLimiterFactory
{
    public function __construct(private readonly RateLimiterFactory $rateLimiterFactory)
    {
    }

    #[NewOptionalParameter(version: 'v6.8.0', parameterName: 'channelId', parameterType: '?string', defaultValue: null, description: 'Sales channel id used to resolve sales-channel scoped limits for the system_config policy. Callers passing it should also include it in the limiter key, as persisted buckets keep the limits they were created with.')]
    public function create(?string $key = null/* , ?string $channelId = null */): LimiterInterface
    {
        /** @deprecated tag:v6.8.0 - Remove next line as $channelId will become a part of method signature */
        /** @var string|null $channelId */
        $channelId = \func_get_args()[1] ?? null;

        if (DisableRateLimiterCompilerPass::isDisabled()) {
            return new NoLimiter();
        }

        return $this->rateLimiterFactory->create($key, $channelId);
    }
}
