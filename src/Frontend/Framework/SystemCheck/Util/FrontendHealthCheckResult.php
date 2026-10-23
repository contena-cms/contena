<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\SystemCheck\Util;

use Contena\Core\Framework\Struct\Struct;

/**
 * @internal
 */
class FrontendHealthCheckResult extends Struct
{
    private function __construct(
        public readonly string $frontendUrl,
        public readonly int $responseCode,
        public readonly float $responseTime,
        public readonly ?string $errorMessage,
    ) {
    }

    public static function create(string $frontendUrl, int $responseCode, float $responseTime, ?string $errorMessage = null): self
    {
        return new self($frontendUrl, $responseCode, $responseTime, $errorMessage);
    }
}
