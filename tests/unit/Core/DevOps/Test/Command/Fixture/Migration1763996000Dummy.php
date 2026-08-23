<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps\Test\Command\Fixture;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1763996000Dummy extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763996000;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
