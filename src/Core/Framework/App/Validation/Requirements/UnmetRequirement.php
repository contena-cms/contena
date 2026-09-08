<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Requirements;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
class UnmetRequirement
{
    public function __construct(
        public readonly string $appName,
        public readonly string $requirementName,
        public readonly string $actionableResolution,
    ) {
    }
}
