<?php declare(strict_types=1);

namespace Contena\Core\Framework\Telemetry\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Contena\Core\Framework\Adapter\Kernel\KernelFactory;

/**
 * Lightweight DBAL driver middleware that counts every executed SQL statement into a shared
 * {@see QueryCounter}. Registered on the `default` connection in {@see KernelFactory}.
 * It only increments an integer, so performance influence is negligible.
 *
 * @internal
 */
final class QueryCountMiddleware implements Middleware
{
    private readonly QueryCounter $counter;

    public function __construct(?QueryCounter $counter = null)
    {
        $this->counter = $counter ?? new QueryCounter();
    }

    public function wrap(Driver $driver): Driver
    {
        return new QueryCountDriver($driver, $this->counter);
    }

    public function getCounter(): QueryCounter
    {
        return $this->counter;
    }
}
