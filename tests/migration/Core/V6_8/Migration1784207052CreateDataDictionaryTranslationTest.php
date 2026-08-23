<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1784207052CreateDataDictionaryTranslation;

/**
 * @internal
 */
#[CoversClass(Migration1784207052CreateDataDictionaryTranslation::class)]
class Migration1784207052CreateDataDictionaryTranslationTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreatesDataDictionaryTranslationTableIdempotently(): void
    {
        $migration = new Migration1784207052CreateDataDictionaryTranslation();

        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (['data_dictionary_id', 'language_id', 'label', 'description', 'created_at', 'updated_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'data_dictionary_translation', $column), $column);
        }

        static::assertFalse(TableHelper::getColumnOfTable($this->connection, 'data_dictionary_translation', 'label')->isNotNull);
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'data_dictionary_translation', 'fk.data_dictionary_translation.data_dictionary_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'data_dictionary_translation', 'fk.data_dictionary_translation.language_id'));
    }
}
