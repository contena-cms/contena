<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NonStandardFkGuardRule;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1785000001UnguardedDdl extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785000001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `blog` ADD COLUMN `foo` VARCHAR(32) NULL');

        $connection->executeStatement('CREATE INDEX `idx.blog.foo` ON `blog` (`foo`)');

        $connection->executeStatement('DROP INDEX `idx.blog.foo` ON `blog`');

        // Not in TABLES_WITH_KNOWN_DRIFT, so not reported
        $connection->executeStatement('ALTER TABLE `order_line_item` ADD COLUMN `foo` VARCHAR(32) NULL');
        $connection->executeStatement('ALTER TABLE `media` ADD COLUMN `foo` VARCHAR(32) NULL');

        // Not DDL, so not reported
        $connection->executeStatement('UPDATE `blog` SET `foo` = NULL');
    }
}
