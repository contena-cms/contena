<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[CoversNothing]
class ConsolidatedCoreSchemaTest extends TestCase
{
    private const array EXCLUDED_TABLES = [
        'app',
        'cart',
        'category',
        'cms_page',
        'currency',
        'customer',
        'order',
        'payment_method',
        'product',
        'promotion',
        'sales_channel',
        'shipping_method',
        'tax',
    ];

    private string $projectRoot;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->projectRoot = \dirname(__DIR__, 4);
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testEveryRetainedTableHasExactlyOneMigrationStep(): void
    {
        $tables = [];

        foreach ($this->createMigrationFiles() as $file) {
            $contents = file_get_contents($file);
            static::assertIsString($contents);

            $matches = [];
            static::assertGreaterThan(0, preg_match_all('/CREATE TABLE IF NOT EXISTS `([^`]+)`/', $contents, $matches), $file);

            foreach ($matches[1] as $table) {
                static::assertArrayNotHasKey($table, $tables, $table);
                $tables[$table] = $file;
                static::assertTrue(TableHelper::tableExists($this->connection, $table), $table);
            }
        }

        static::assertCount(90, $tables);
    }

    public function testConsolidatedMigrationsCanBeExecutedAgain(): void
    {
        foreach ($this->migrationFiles() as $file) {
            $class = 'Contena\\Core\\Migration\\V6_8\\' . pathinfo($file, \PATHINFO_FILENAME);

            static::assertTrue(class_exists($class), $class);

            $migration = new $class();
            static::assertInstanceOf(MigrationStep::class, $migration);

            $migration->update($this->connection);
        }
    }

    public function testBootstrapSchemaOnlyContainsPluginAndMigrationTables(): void
    {
        $schema = file_get_contents($this->projectRoot . '/src/Core/schema.sql');
        static::assertIsString($schema);

        $matches = [];
        preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)? `([^`]+)`/', $schema, $matches);

        static::assertSame(['plugin', 'migration'], $matches[1]);
    }

    public function testExcludedDomainTablesDoNotExist(): void
    {
        foreach (self::EXCLUDED_TABLES as $table) {
            static::assertFalse(TableHelper::tableExists($this->connection, $table), $table);
        }
    }

    public function testDefaultMediaThumbnailSizeExists(): void
    {
        static::assertSame(
            1,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `media_thumbnail_size` WHERE `width` = 200 AND `height` = 200'
            )
        );
    }

    /**
     * @return list<string>
     */
    private function createMigrationFiles(): array
    {
        $files = glob($this->projectRoot . '/src/Core/Migration/V6_8/Migration*Create*.php');
        static::assertIsArray($files);
        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function migrationFiles(): array
    {
        $files = glob($this->projectRoot . '/src/Core/Migration/V6_8/Migration*.php');
        static::assertIsArray($files);
        sort($files);

        return $files;
    }
}
