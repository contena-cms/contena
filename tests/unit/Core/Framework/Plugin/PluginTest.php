<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use CtTestPlugin\CtTestPlugin;

/**
 * @internal
 */
#[CoversClass(CtTestPlugin::class)]
class PluginTest extends TestCase
{
    private static string $ctTestPluginPath;

    private static string $symlinkedCtTestPluginPath;

    public static function setUpBeforeClass(): void
    {
        $pluginsDir = __DIR__ . '/../../../../../tests/integration/Core/Framework/Plugin/_fixtures/plugins/';
        $ctTestPluginPath = realpath($pluginsDir . '/CtTestPlugin');
        static::assertIsString($ctTestPluginPath);
        self::$ctTestPluginPath = $ctTestPluginPath;

        self::$symlinkedCtTestPluginPath = sys_get_temp_dir() . '/SymlinkedCtTest_' . uniqid();
        symlink(self::$ctTestPluginPath, self::$symlinkedCtTestPluginPath);

        require_once self::$ctTestPluginPath . '/src/CtTestPlugin.php';
    }

    public static function tearDownAfterClass(): void
    {
        if (\is_dir(self::$symlinkedCtTestPluginPath) && is_link(self::$symlinkedCtTestPluginPath)) {
            unlink(self::$symlinkedCtTestPluginPath);
        }
    }

    public function testGetPathWithNonSymlinkedPlugin(): void
    {
        $plugin = new CtTestPlugin(true, self::$ctTestPluginPath);

        static::assertSame(self::$ctTestPluginPath . '/src', $plugin->getPath());
    }

    public function testGetPathWithSymlinkedPlugin(): void
    {
        $plugin = new CtTestPlugin(true, self::$symlinkedCtTestPluginPath);

        static::assertSame(self::$symlinkedCtTestPluginPath . '/src', $plugin->getPath());
    }

    public function testGetBasePath(): void
    {
        $plugin = new CtTestPlugin(true, self::$symlinkedCtTestPluginPath);

        static::assertSame(self::$symlinkedCtTestPluginPath, $plugin->getBasePath());
    }

    public function testGetBasePathIncludingSlash(): void
    {
        $plugin = new CtTestPlugin(true, 'somePlugin', '/www/');

        static::assertSame('/www/somePlugin', $plugin->getBasePath());
    }

    public function testGetPathWithTrailingSlashBasePath(): void
    {
        $plugin = new CtTestPlugin(true, self::$ctTestPluginPath . '/');

        static::assertSame(self::$ctTestPluginPath . '/src', $plugin->getPath());
        static::assertStringNotContainsString('//', $plugin->getPath());
    }
}
