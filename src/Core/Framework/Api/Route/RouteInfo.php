<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Route;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
readonly class RouteInfo
{
    /**
     * @param string[] $methods
     */
    public function __construct(
        public string $path,
        public array $methods,
    ) {
    }
}
