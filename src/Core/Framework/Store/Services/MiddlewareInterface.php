<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Services;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
interface MiddlewareInterface
{
    public function __invoke(callable $handler): callable;
}
