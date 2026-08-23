<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1784207053CreateDataDictionaryItem;

/**
 * @internal
 */
#[CoversClass(Migration1784207053CreateDataDictionaryItem::class)]
class Migration1784207053CreateDataDictionaryItemTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreatesDataDictionaryItemTableIdempotently(): void
    {
        $migration = new Migration1784207053CreateDataDictionaryItem();

        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (['id', 'dictionary_id', 'parent_id', 'code', 'value', 'position', 'active', 'system_locked', 'custom_fields', 'created_at', 'updated_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'data_dictionary_item', $column), $column);
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'data_dictionary_item', 'uniq.data_dictionary_item.dictionary_code'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'data_dictionary_item', 'idx.data_dictionary_item.dictionary_position'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'data_dictionary_item', 'fk.data_dictionary_item.dictionary_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'data_dictionary_item', 'fk.data_dictionary_item.parent_id'));
    }
}
