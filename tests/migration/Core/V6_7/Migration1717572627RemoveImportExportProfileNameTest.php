<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1717572627RemoveImportExportProfileName;

/**
 * @internal
 */
#[CoversClass(Migration1717572627RemoveImportExportProfileName::class)]
class Migration1717572627RemoveImportExportProfileNameTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1717572627, (new Migration1717572627RemoveImportExportProfileName())->getCreationTimestamp());
    }

    public function testUpdateDestructiveRemovesColumn(): void
    {
        $exists = TableHelper::columnExists($this->connection, 'import_export_profile', 'name');

        if (!$exists) {
            $this->addColumn();
        }

        $migration = new Migration1717572627RemoveImportExportProfileName();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'import_export_profile', 'name'));

        if ($exists) {
            $this->addColumn();
        }
    }

    private function addColumn(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE `import_export_profile` ADD COLUMN `name` VARCHAR(255) DEFAULT NULL'
        );
    }
}
