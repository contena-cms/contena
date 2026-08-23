<?php
declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Plugin\KernelPluginLoader;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Composer\ComposerInfoProvider;
use Contena\Core\Framework\Adapter\Composer\ComposerPackage;
use Contena\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Contena\Core\Framework\Test\Plugin\PluginIntegrationTestBehaviour;
use CtTestComposerLoaded\CtTestComposerLoaded;

/**
 * @internal
 */
class ComposerPluginLoaderTest extends TestCase
{
    use PluginIntegrationTestBehaviour;

    protected function tearDown(): void
    {
        parent::tearDown();
        ComposerInfoProvider::reset();
    }

    public function testNoPlugins(): void
    {
        ComposerInfoProvider::fake([]);

        $loader = new ComposerPluginLoader($this->classLoader, null);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertEmpty($loader->getPluginInfos());
        static::assertEmpty($loader->getPluginInstances()->all());
    }

    public function testWithInvalidPlugins(): void
    {
        ComposerInfoProvider::fake([
            new ComposerPackage(
                name: 'ct/broken1',
                version: '1.0.0',
                prettyVersion: '1.0.0.0',
                path: '/tmp/some-random-folder',
            ),
            new ComposerPackage(
                name: 'ct/broken2',
                version: '1.0.0',
                prettyVersion: '1.0.0.0',
                path: __DIR__ . '/../_fixture/plugins/CtTestInvalidComposerJson',
            ),
        ]);

        $loader = new ComposerPluginLoader($this->classLoader, null);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertEmpty($loader->getPluginInfos());
        static::assertEmpty($loader->getPluginInstances()->all());
    }

    public function testLoadsPlugins(): void
    {
        $this->loadComposerLoadedPluginFixture();

        $loader = new ComposerPluginLoader($this->classLoader, null);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertNotEmpty($loader->getPluginInfos());

        $entry = array_find($loader->getPluginInfos(), static fn (array $plugin) => $plugin['name'] === 'CtTestComposerLoaded');
        static::assertNotNull($entry);

        static::assertSame('CtTestComposerLoaded', $entry['name']);
        static::assertSame(CtTestComposerLoaded::class, $entry['baseClass']);
        static::assertTrue($entry['active']);
    }

    public function testFetchPluginInfos(): void
    {
        $this->loadComposerLoadedPluginFixture();

        $loader = new ComposerPluginLoader($this->classLoader, null);
        $plugins = $loader->fetchPluginInfos();

        static::assertNotEmpty($plugins);

        $pluginNames = array_column($plugins, 'name');
        static::assertContains('CtTestComposerLoaded', $pluginNames);

        $pluginBaseClasses = array_column($plugins, 'baseClass');
        static::assertContains(CtTestComposerLoaded::class, $pluginBaseClasses);
    }

    private function loadComposerLoadedPluginFixture(): void
    {
        // We assume that the class can be found from the autoloader without modifying them
        require_once __DIR__ . '/../_fixtures/plugins/CtTestComposerLoaded/src/CtTestComposerLoaded.php';

        ComposerInfoProvider::fake([
            new ComposerPackage(
                name: 'ct/composer-loaded',
                version: '1.0.0',
                prettyVersion: '1.0.0.0',
                path: __DIR__ . '/../_fixtures/plugins/CtTestComposerLoaded',
            ),
        ]);
    }
}
