<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Route;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
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
