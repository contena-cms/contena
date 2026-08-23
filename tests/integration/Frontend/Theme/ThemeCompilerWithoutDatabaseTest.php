<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FileCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\MD5ThemePathBuilder;
use Contena\Frontend\Theme\ScssPhpCompiler;
use Contena\Frontend\Theme\ThemeCompiler;
use Contena\Frontend\Theme\ThemeFileResolver;
use Contena\Frontend\Theme\ThemeFilesystemResolver;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;

/**
 * @internal
 */
class ThemeCompilerWithoutDatabaseTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use EnvTestBehaviour;
    use KernelTestBehaviour;

    /**
     * Theme compilation must remain usable when the database is unavailable.
     */
    public function testCompileWithoutDatabase(): void
    {
        $this->stopTransactionAfter();
        $this->setEnvVars(['DATABASE_URL' => 'mysql://user:no@mysql:3306/test_db']);
        KernelLifecycleManager::bootKernel(false, 'noDB');

        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        $testFolder = $projectDir . '/bla';
        mkdir($testFolder);

        $resolver = $this->createMock(ThemeFileResolver::class);
        $resolver->method('resolveFiles')->willReturn([
            ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
            ThemeFileResolver::STYLE_FILES => new FileCollection(),
        ]);

        $config = new FrontendPluginConfiguration('test');
        $config->setAssetPaths(['bla']);

        $compiler = new ThemeCompiler(
            new Filesystem(new InMemoryFilesystemAdapter()),
            new Filesystem(new InMemoryFilesystemAdapter()),
            new Filesystem(new InMemoryFilesystemAdapter()),
            new CopyBatchInputFactory(),
            $resolver,
            true,
            static::getContainer()->get('event_dispatcher'),
            $this->createMock(ThemeFilesystemResolver::class),
            ['theme' => new UrlPackage(['http://localhost'], new EmptyVersionStrategy())],
            $this->createMock(CacheInvalidator::class),
            $this->createMock(LoggerInterface::class),
            new MD5ThemePathBuilder(),
            static::getContainer()->get(ScssPhpCompiler::class),
        );

        $exception = null;
        try {
            $compiler->compileTheme(
                '98432def39fc4624b33213a56b8c944d',
                'test',
                $config,
                new FrontendPluginConfigurationCollection(),
                true,
                Context::createDefaultContext()
            );
        } catch (\Throwable $throwable) {
            $exception = $throwable->getMessage();
        }

        $this->resetEnvVars();
        KernelLifecycleManager::bootKernel();
        $this->startTransactionBefore();
        rmdir($testFolder);

        static::assertNull($exception, 'ThemeCompiler should be executable without a database connection: ' . $exception);
    }
}
