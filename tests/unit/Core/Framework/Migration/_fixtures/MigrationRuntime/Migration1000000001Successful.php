<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime;

use Contena\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * Fixture for MigrationRuntimeTest — executed against a stubbed connection.
 *
 * @internal
 */
class Migration1000000001Successful extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1000000001;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
