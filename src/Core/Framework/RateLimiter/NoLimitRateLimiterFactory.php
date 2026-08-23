<?php
declare(strict_types=1);

namespace Contena\Core\Framework\RateLimiter;

use Contena\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\Policy\NoLimiter;

class NoLimitRateLimiterFactory extends RateLimiterFactory
{
    public function __construct(private readonly RateLimiterFactory $rateLimiterFactory)
    {
    }

    public function create(?string $key = null): LimiterInterface
    {
        if (DisableRateLimiterCompilerPass::isDisabled()) {
            return new NoLimiter();
        }

        return $this->rateLimiterFactory->create($key);
    }
}
