<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1784207051CreateDataDictionary;

/**
 * @internal
 */
#[CoversClass(Migration1784207051CreateDataDictionary::class)]
class Migration1784207051CreateDataDictionaryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreatesDataDictionaryTableIdempotently(): void
    {
        $migration = new Migration1784207051CreateDataDictionary();

        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (['id', 'technical_name', 'active', 'system_locked', 'created_at', 'updated_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'data_dictionary', $column), $column);
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'data_dictionary', 'uniq.data_dictionary.technical_name'));
    }
}
