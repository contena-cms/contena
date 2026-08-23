<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NonStandardFkGuardRule;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1785716585UnguardedDdl extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785716585;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `media` ADD COLUMN `foo` VARCHAR(32) NULL');
        $connection->executeStatement('CREATE INDEX `idx.media.foo` ON `media` (`foo`)');
        $connection->executeStatement('DROP INDEX `idx.media.foo` ON `media`');
        $connection->executeStatement('UPDATE `media` SET `foo` = NULL');
    }
}
