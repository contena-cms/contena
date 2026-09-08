<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Database;

use Contena\Core\Kernel;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;

/**
 * @internal
 */
class ReplicaConnection
{
    public static function ensurePrimary(): void
    {
        $connection = Kernel::getConnection();

        if ($connection instanceof PrimaryReadReplicaConnection) {
            $connection->ensureConnectedToPrimary();
        }
    }
}
