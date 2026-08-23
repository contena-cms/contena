<?php declare(strict_types=1);

namespace Contena\Core\Framework\Telemetry\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Unit\Core\Framework\Telemetry\Doctrine\QueryCountMiddlewareTest
 */
final class QueryCountDriver extends AbstractDriverMiddleware
{
    public function __construct(
        Driver $driver,
        private readonly QueryCounter $counter,
    ) {
        parent::__construct($driver);
    }

    public function connect(
        #[\SensitiveParameter]
        array $params,
    ): DriverConnection {
        return new QueryCountConnection(parent::connect($params), $this->counter);
    }
}
