<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\Migration\_test_migrations_valid;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1;
    }

    public function update(Connection $connection): void
    {
        // nth
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
