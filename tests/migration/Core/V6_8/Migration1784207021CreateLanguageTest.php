<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_8\Migration1784207021CreateLanguage;

/**
 * @internal
 */
#[CoversClass(Migration1784207021CreateLanguage::class)]
class Migration1784207021CreateLanguageTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreatesFinalLocalizationSchemaIdempotently(): void
    {
        $migration = new Migration1784207021CreateLanguage();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'language'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'language', 'translation_auto_update'));

        $column = $this->connection
            ->createSchemaManager()
            ->introspectTableByUnquotedName('language')
            ->getColumn('translation_auto_update');

        static::assertTrue($column->getNotnull());
        static::assertSame('1', (string) $column->getDefault());

        static::assertTrue(TableHelper::tableExists($this->connection, 'snippet_set'));
        static::assertTrue(TableHelper::tableExists($this->connection, 'snippet'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'snippet', 'uniq.snippet_set_id_translation_key'));
    }

    public function testUsesTheConsolidatedDevelopmentBaselineTimestamp(): void
    {
        static::assertSame(1784207021, new Migration1784207021CreateLanguage()->getCreationTimestamp());
    }
}
