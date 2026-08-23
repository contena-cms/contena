<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Frontend\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Util\Database\TableHelper;
use Contena\Frontend\Migration\V6_8\Migration1785943081CreateTheme;

/**
 * @internal
 */
#[CoversClass(Migration1785943081CreateTheme::class)]
class Migration1785943081CreateThemeTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DROP TABLE IF EXISTS `footer_content_layout`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `header_content_layout`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `theme_channel`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `theme_runtime_config`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `theme_child`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `theme_media`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `theme_translation`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `theme`');
    }

    public function testCreatesThemeBaselineIdempotently(): void
    {
        $migration = new Migration1785943081CreateTheme();

        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (['theme', 'theme_translation', 'theme_media', 'theme_child', 'theme_runtime_config', 'theme_channel', 'header_content_layout', 'footer_content_layout'] as $table) {
            static::assertTrue(TableHelper::tableExists($this->connection, $table), $table);
        }

        foreach (['technical_name', 'theme_json', 'base_config', 'config_values', 'active'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'theme', $column), $column);
        }

        foreach (['resolved_config', 'view_inheritance', 'script_files', 'icon_sets', 'import_map'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'theme_runtime_config', $column), $column);
        }

        static::assertFalse(TableHelper::getColumnOfTable($this->connection, 'theme', 'updated_at')->isNotNull);
        static::assertTrue(TableHelper::getColumnOfTable($this->connection, 'theme_runtime_config', 'updated_at')->isNotNull);

        static::assertTrue(TableHelper::indexExists($this->connection, 'theme_runtime_config', 'uidx.technical_name'));
        static::assertSame('UNIQUE', TableHelper::getIndexOfTable($this->connection, 'theme_runtime_config', 'uidx.technical_name')->type);
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'theme_media', 'fk.theme_media.media_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'theme_child', 'fk.theme_child.child_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'theme_channel', 'fk.theme_channel.theme_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'theme_channel', 'fk.theme_channel.channel_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'header_content_layout', 'fk.header_content_layout.channel_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'footer_content_layout', 'fk.footer_content_layout.channel_id'));
        static::assertSame('1', $this->connection->fetchOne('SELECT COUNT(*) FROM `media_default_folder` WHERE `entity` = :entity', ['entity' => 'theme']));
    }
}
