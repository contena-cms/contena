<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1752750086AddIndexToOrderLineItemCreateAndUpdate;

/**
 * @internal
 */
#[CoversClass(Migration1752750086AddIndexToOrderLineItemCreateAndUpdate::class)]
class Migration1752750086AddIndexToOrderLineItemCreateAndUpdateTest extends NonStandardFkGuardMigrationTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1752750086, (new Migration1752750086AddIndexToOrderLineItemCreateAndUpdate())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1752750086AddIndexToOrderLineItemCreateAndUpdate();
        static::assertSame(1752750086, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();

        $migration = new Migration1752750086AddIndexToOrderLineItemCreateAndUpdate();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'order_line_item', 'idx.order_line_item_created_updated'));
    }

    public function testIndexCreationSurvivesNonStandardForeignKeyGuard(): void
    {
        $this->assertIndexCreationSurvivesNonStandardForeignKeyGuard(new Migration1752750086AddIndexToOrderLineItemCreateAndUpdate(), 'order_line_item');
    }

    private function rollback(): void
    {
        if (TableHelper::indexExists($this->connection, 'order_line_item', 'idx.order_line_item_created_updated')) {
            $this->connection->executeStatement('DROP INDEX `idx.order_line_item_created_updated` ON `order_line_item`');
        }
    }
}
