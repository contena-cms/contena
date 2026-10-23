<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NonStandardFkGuardRule;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1785000002GuardedDdl extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785000002;
    }

    public function update(Connection $connection): void
    {
        $this->executeDdlStatement($connection, 'ALTER TABLE `blog` ADD COLUMN `foo` VARCHAR(32) NULL');

        $this->executeDdlStatement($connection, 'CREATE INDEX `idx.blog.foo` ON `blog` (`foo`)');

        // The MigrationStep DDL helpers run through the guard themselves
        $this->addColumn($connection, 'blog', 'bar', 'VARCHAR(32)');
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'blog', 'foo');
    }
}
