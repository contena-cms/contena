<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\ExtensionExtractor;
use Contena\Core\Framework\Plugin\PluginEntity;
use Contena\Core\Framework\Plugin\PluginException;
use Contena\Core\Framework\Plugin\PluginManagementService;
use Contena\Core\Framework\Plugin\PluginService;
use Contena\Core\Framework\Plugin\PluginZipDetector;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(PluginManagementService::class)]
class PluginManagementServiceTest extends TestCase
{
    public function testExtractPluginWithDetectedPlugin(): void
    {
        $pluginService = static::createStub(PluginService::class);

        $pluginZipDetector = $this->createMock(PluginZipDetector::class);
        $pluginZipDetector->expects($this->once())
            ->method('detect')
            ->with('/some/zip/file.zip')
            ->willReturn(PluginManagementService::PLUGIN);

        $extractor = $this->createMock(ExtensionExtractor::class);
        $extractor->expects($this->once())
            ->method('extract')
            ->with('/some/zip/file.zip');

        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->once())
            ->method('clearContainerCache');

        $pluginManagementService = new PluginManagementService(
            '',
            $pluginZipDetector,
            $extractor,
            $pluginService,
            static::createStub(Filesystem::class),
            $cacheClearer
        );

        $pluginManagementService->extractPluginZip(
            '/some/zip/file.zip',
        );
    }

    public function testDeleteWhenManaged(): void
    {
        $fs = $this->createMock(Filesystem::class);
        $fs->expects($this->never())->method('remove');

        $pluginManagementService = new PluginManagementService(
            '',
            static::createStub(PluginZipDetector::class),
            static::createStub(ExtensionExtractor::class),
            static::createStub(PluginService::class),
            $fs,
            static::createStub(CacheClearer::class)
        );

        $plugin = new PluginEntity();
        $plugin->setManagedByComposer(true);
        $plugin->setPath('vendor/test');
        $plugin->setName('Test');

        $this->expectExceptionObject(PluginException::cannotDeleteManaged($plugin->getName()));
        $pluginManagementService->deletePlugin($plugin, Context::createDefaultContext());
    }

    public function testDeleteWhenManagedInStaticPlugins(): void
    {
        $fs = $this->createMock(Filesystem::class);
        $fs->expects($this->never())->method('remove');

        $pluginManagementService = new PluginManagementService(
            '',
            static::createStub(PluginZipDetector::class),
            static::createStub(ExtensionExtractor::class),
            static::createStub(PluginService::class),
            $fs,
            static::createStub(CacheClearer::class)
        );

        $plugin = new PluginEntity();
        $plugin->setManagedByComposer(true);
        $plugin->setPath('custom/static-plugins/test');
        $plugin->setName('Test');

        static::expectException(PluginException::class);
        $pluginManagementService->deletePlugin($plugin, Context::createDefaultContext());
    }

    public function testDeleteWhenManagedInCustomPluginsStillWorks(): void
    {
        $fs = $this->createMock(Filesystem::class);
        $fs->expects($this->once())->method('remove');

        $pluginManagementService = new PluginManagementService(
            '',
            static::createStub(PluginZipDetector::class),
            static::createStub(ExtensionExtractor::class),
            static::createStub(PluginService::class),
            $fs,
            static::createStub(CacheClearer::class)
        );

        $plugin = new PluginEntity();
        $plugin->setManagedByComposer(true);
        $plugin->setPath('custom/plugins//test');
        $plugin->setName('Test');

        $pluginManagementService->deletePlugin($plugin, Context::createDefaultContext());
    }
}
