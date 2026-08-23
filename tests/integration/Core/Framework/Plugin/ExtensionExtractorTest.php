<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Plugin;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Plugin\ExtensionExtractor;
use Contena\Core\Framework\Plugin\PluginException;
use Contena\Core\Framework\Plugin\PluginManagementService;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
class ExtensionExtractorTest extends TestCase
{
    use KernelTestBehaviour;

    protected ContainerInterface $container;

    private Filesystem $filesystem;

    private ExtensionExtractor $extractor;

    protected function setUp(): void
    {
        $this->container = static::getContainer();
        $this->filesystem = $this->container->get(Filesystem::class);
        $this->extractor = new ExtensionExtractor(
            [
                PluginManagementService::PLUGIN => __DIR__ . '/_fixtures/plugins',
            ],
            $this->filesystem
        );
    }

    public function testExtractPlugin(): void
    {
        $this->filesystem->copy(__DIR__ . '/_fixtures/archives/CtFashionTheme.zip', __DIR__ . '/_fixtures/CtFashionTheme.zip');

        $archive = __DIR__ . '/_fixtures/CtFashionTheme.zip';

        $this->extractor->extract($archive, false, PluginManagementService::PLUGIN);

        static::assertFileExists(__DIR__ . '/_fixtures/plugins/CtFashionTheme');
        static::assertFileExists(__DIR__ . '/_fixtures/plugins/CtFashionTheme/CtFashionTheme.php');

        $this->filesystem->remove(__DIR__ . '/_fixtures/plugins/CtFashionTheme');
    }

    public function testExtractWithPathTraversal(): void
    {
        $zipPath = __DIR__ . '/_fixtures/DirectoryTraversal.zip';

        $archive = new \ZipArchive();
        $archive->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $archive->addEmptyDir('MyPlugin');
        $archive->addFromString('MyPlugin/../../evil.php', 'This should not exist outside of the MyPlugin directory');
        $archive->close();

        $this->expectExceptionObject(PluginException::pluginExtractionError('Directory Traversal detected'));
        $this->extractor->extract($zipPath, false, PluginManagementService::PLUGIN);
    }
}
