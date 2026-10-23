<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Core\Migration\V6_7\Migration1785327592AddTranslationAutoUpdateToLanguage;
use Contena\Core\System\Language\LanguageDefinition;

/**
 * @internal
 */
#[CoversClass(Migration1785327592AddTranslationAutoUpdateToLanguage::class)]
class Migration1785327592AddTranslationAutoUpdateToLanguageTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationAddsColumnDefaultingToOnAndIsIdempotent(): void
    {
        $this->rollback();

        static::assertFalse($this->columnExists());

        $languageCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `language`');
        static::assertGreaterThan(0, $languageCount);

        $migration = new Migration1785327592AddTranslationAutoUpdateToLanguage();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->columnExists());

        $column = $this->connection
            ->createSchemaManager()
            ->introspectTableByUnquotedName(LanguageDefinition::ENTITY_NAME)
            ->getColumn('translation_auto_update');

        static::assertTrue($column->getNotnull());
        static::assertSame('1', (string) $column->getDefault());

        // existing languages default to enabled auto-update
        static::assertSame(
            $languageCount,
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `language` WHERE `translation_auto_update` = 1')
        );
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1785327592,
            (new Migration1785327592AddTranslationAutoUpdateToLanguage())->getCreationTimestamp()
        );
    }

    private function columnExists(): bool
    {
        return TableHelper::columnExists($this->connection, LanguageDefinition::ENTITY_NAME, 'translation_auto_update');
    }

    private function rollback(): void
    {
        if (!$this->columnExists()) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `language` DROP COLUMN `translation_auto_update`');
    }
}
