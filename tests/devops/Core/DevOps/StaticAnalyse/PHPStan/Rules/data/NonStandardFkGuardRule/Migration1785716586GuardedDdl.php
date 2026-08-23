<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NonStandardFkGuardRule;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1785716586GuardedDdl extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785716586;
    }

    public function update(Connection $connection): void
    {
        $this->executeDdlStatement($connection, 'ALTER TABLE `media` ADD COLUMN `foo` VARCHAR(32) NULL');
        $this->executeDdlStatement($connection, 'CREATE INDEX `idx.media.foo` ON `media` (`foo`)');
        $this->addColumn($connection, 'media', 'bar', 'VARCHAR(32)');
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'media', 'foo');
    }
}
