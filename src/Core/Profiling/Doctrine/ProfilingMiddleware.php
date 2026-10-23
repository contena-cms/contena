<?php declare(strict_types=1);

namespace Contena\Core\Profiling\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Contena\Core\Framework\Log\Package;
use Symfony\Bridge\Doctrine\Middleware\Debug\Driver as DebugDriver;
use Symfony\Component\Stopwatch\Stopwatch;

class ProfilingMiddleware implements Middleware
{
    private const CONNECTION = 'default';

    public function __construct(
        public BacktraceDebugDataHolder $debugDataHolder = new BacktraceDebugDataHolder([self::CONNECTION]),
    ) {
    }

    public function wrap(Driver $driver): DebugDriver
    {
        return new DebugDriver(
            $driver,
            $this->debugDataHolder,
            new Stopwatch(),
            self::CONNECTION
        );
    }
}
