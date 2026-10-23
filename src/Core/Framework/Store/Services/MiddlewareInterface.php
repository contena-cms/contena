<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Services;


/**
 * @internal
 */
interface MiddlewareInterface
{
    public function __invoke(callable $handler): callable;
}
