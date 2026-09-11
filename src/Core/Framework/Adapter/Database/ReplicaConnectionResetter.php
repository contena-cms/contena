<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;

/**
 * @internal
 */
class ReplicaConnectionResetter
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function reset(): void
    {
        if (!$this->connection instanceof PrimaryReadReplicaConnection) {
            return;
        }

        if ($this->connection->getTransactionNestingLevel() > 0) {
            return;
        }

        if (!$this->connection->isConnectedToPrimary()) {
            return;
        }

        $this->connection->ensureConnectedToReplica();
    }
}
