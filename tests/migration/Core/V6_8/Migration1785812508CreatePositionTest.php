<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1785812508CreatePosition;

/**
 * @internal
 */
#[CoversClass(Migration1785812508CreatePosition::class)]
class Migration1785812508CreatePositionTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreatesPositionAggregateAndUserMappingIdempotently(): void
    {
        $migration = new Migration1785812508CreatePosition();

        $migration->update($this->connection);
        $migration->update($this->connection);
        $migration->updateDestructive($this->connection);

        foreach (['position', 'position_translation', 'user_position'] as $table) {
            static::assertTrue(TableHelper::tableExists($this->connection, $table), $table);
        }

        foreach (['id', 'code', 'position', 'active', 'custom_fields', 'created_at', 'updated_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'position', $column), $column);
        }

        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'position_translation', 'fk.position_translation.position_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'user_position', 'fk.user_position.user_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'user_position', 'fk.user_position.position_id'));
        static::assertFalse(TableHelper::columnExists($this->connection, 'user', 'title'));
    }
}
