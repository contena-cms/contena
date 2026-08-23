<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Plugin;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\DevOps\StaticAnalyze\StaticAnalyzeKernel;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Kernel\KernelFactory;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\ExtensionExtractor;
use Contena\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Contena\Core\Framework\Plugin\PluginManagementService;
use Contena\Core\Framework\Plugin\PluginService;
use Contena\Core\Framework\Plugin\PluginZipDetector;
use Contena\Core\Framework\Plugin\Util\PluginFinder;
use Contena\Core\Framework\Test\Plugin\PluginTestsHelper;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Kernel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal
 */
#[Group('slow')]
class PluginManagementServiceTest extends TestCase
{
    use KernelTestBehaviour;
    use PluginTestsHelper;

    private const string TEST_PLUGIN_ZIP_NAME = 'CtFashionTheme.zip';
    private const string FIXTURE_PATH = __DIR__ . '/../../../../../tests/integration/Core/Framework/Plugin/_fixtures/';
    private const string PLUGIN_ZIP_FIXTURE_PATH = self::FIXTURE_PATH . self::TEST_PLUGIN_ZIP_NAME;
    private const string PLUGINS_PATH = self::FIXTURE_PATH . 'plugins';
    private const string PLUGIN_FASHION_THEME_PATH = self::PLUGINS_PATH . '/CtFashionTheme';
    private const string PLUGIN_FASHION_THEME_BASE_CLASS_PATH = self::PLUGIN_FASHION_THEME_PATH . '/CtFashionTheme.php';

    private Filesystem $filesystem;

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->filesystem = static::getContainer()->get(Filesystem::class);

        $this->cacheDir = $this->createTestCacheDirectory();

        $this->filesystem->copy(
            self::FIXTURE_PATH . 'archives/' . self::TEST_PLUGIN_ZIP_NAME,
            self::PLUGIN_ZIP_FIXTURE_PATH
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(self::PLUGIN_FASHION_THEME_PATH);
        $this->filesystem->remove(self::PLUGIN_ZIP_FIXTURE_PATH);
        $this->filesystem->remove($this->cacheDir);

        Kernel::getConnection()->executeStatement('DELETE FROM plugin');
    }

    public function testUploadPlugin(): void
    {
        $pluginFile = $this->createUploadedFile();
        $this->getPluginManagementService()->uploadPlugin($pluginFile, Context::createDefaultContext());

        static::assertFileExists(self::PLUGIN_FASHION_THEME_PATH);
        static::assertFileExists(self::PLUGIN_FASHION_THEME_BASE_CLASS_PATH);
    }

    public function testExtractPluginZip(): void
    {
        $this->getPluginManagementService()->extractPluginZip(self::PLUGIN_ZIP_FIXTURE_PATH);

        $extractedPlugin = $this->filesystem->exists(self::PLUGIN_FASHION_THEME_PATH);
        $extractedPluginBaseClass = $this->filesystem->exists(self::PLUGIN_FASHION_THEME_BASE_CLASS_PATH);
        $pluginZipExists = $this->filesystem->exists(self::PLUGIN_ZIP_FIXTURE_PATH);
        static::assertTrue($extractedPlugin);
        static::assertTrue($extractedPluginBaseClass);
        static::assertFalse($pluginZipExists);
    }

    public function testExtractPluginZipWithoutDeletion(): void
    {
        $this->getPluginManagementService()->extractPluginZip(self::PLUGIN_ZIP_FIXTURE_PATH, false);

        $extractedPlugin = $this->filesystem->exists(self::PLUGIN_FASHION_THEME_PATH);
        $extractedPluginBaseClass = $this->filesystem->exists(self::PLUGIN_FASHION_THEME_BASE_CLASS_PATH);
        $pluginZipExists = $this->filesystem->exists(self::PLUGIN_ZIP_FIXTURE_PATH);
        static::assertTrue($extractedPlugin);
        static::assertTrue($extractedPluginBaseClass);
        static::assertTrue($pluginZipExists);
    }

    public function testClearContainerCacheWhenPluginZipIsGiven(): void
    {
        $this->getPluginManagementService()->extractPluginZip(self::PLUGIN_ZIP_FIXTURE_PATH);

        static::assertFalse($this->containerCacheExists());
    }

    private function createTestCacheDirectory(): string
    {
        $previousKernelClass = KernelFactory::$kernelClass;

        // We need a new fixed cache dir, therefore, we reuse the StaticAnalyzeKernel class
        KernelFactory::$kernelClass = StaticAnalyzeKernel::class;

        $newTestKernel = KernelFactory::create(
            'test',
            true,
            KernelLifecycleManager::getClassLoader(),
            new StaticKernelPluginLoader(KernelLifecycleManager::getClassLoader()),
            static::getContainer()->get(Connection::class)
        );
        static::assertInstanceOf(Kernel::class, $newTestKernel);
        // reset the kernel class for further tests
        KernelFactory::$kernelClass = $previousKernelClass;
        $newTestKernel->boot();
        $cacheDir = $newTestKernel->getCacheDir();
        $newTestKernel->shutdown();

        return $cacheDir;
    }

    private function createUploadedFile(): UploadedFile
    {
        return new UploadedFile(self::PLUGIN_ZIP_FIXTURE_PATH, self::TEST_PLUGIN_ZIP_NAME, null, null, true);
    }

    private function getPluginManagementService(): PluginManagementService
    {
        return new PluginManagementService(
            self::PLUGINS_PATH,
            new PluginZipDetector(),
            new ExtensionExtractor([
                PluginManagementService::PLUGIN => self::PLUGINS_PATH,
            ], $this->filesystem),
            $this->getPluginService(),
            $this->filesystem,
            $this->getCacheClearer()
        );
    }

    private function getPluginService(): PluginService
    {
        return $this->createPluginService(
            self::FIXTURE_PATH . 'plugins',
            static::getContainer()->getParameter('kernel.project_dir'),
            static::getContainer()->get('plugin.repository'),
            static::getContainer()->get('language.repository'),
            static::getContainer()->get(PluginFinder::class)
        );
    }

    private function getCacheClearer(): CacheClearer
    {
        return new CacheClearer(
            [],
            static::getContainer()->get('cache_clearer'),
            null,
            static::getContainer()->get(CacheInvalidator::class),
            $this->filesystem,
            $this->cacheDir,
            'test',
            false,
            false,
            static::getContainer()->get('messenger.default_bus'),
            static::getContainer()->get('logger'),
            static::getContainer()->get('lock.factory')
        );
    }

    private function containerCacheExists(): bool
    {
        return new Finder()->in($this->cacheDir)->name('*Container*')->depth(0)->count() !== 0;
    }
}
