<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Redis;

use Psr\Container\ContainerInterface;
use Contena\Core\Framework\Adapter\AdapterException;

/**
 * RedisConnection corresponds to a return type of symfony's RedisAdapter::createConnection and may change with symfony update.
 *
 * @phpstan-type RedisConnection \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|\Relay\Relay
 */
class RedisConnectionProvider
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ContainerInterface $serviceLocator,
    ) {
    }

    /**
     * @return RedisConnection
     */
    public function getConnection(string $connectionName)
    {
        if (!$this->hasConnection($connectionName)) {
            throw AdapterException::unknownRedisConnection($connectionName);
        }

        return $this->serviceLocator->get($this->getServiceName($connectionName));
    }

    public function hasConnection(string $connectionName): bool
    {
        return $this->serviceLocator->has($this->getServiceName($connectionName));
    }

    private function getServiceName(string $connectionName): string
    {
        return 'contena.redis.connection.' . $connectionName;
    }
}
