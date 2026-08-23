<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Administration\Snippet;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Contena\Administration\Administration;
use Contena\Administration\Snippet\SnippetException;
use Contena\Administration\Snippet\SnippetFinder;
use Contena\Core\Framework\Plugin;
use Contena\Core\Framework\Plugin\KernelPluginCollection;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Core\Kernel;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Locale\LocaleCollection;
use Contena\Core\System\Locale\LocaleDefinition;
use Contena\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Contena\Core\System\Snippet\DataTransfer\Language\Language as LanguageDto;
use Contena\Core\System\Snippet\DataTransfer\Language\LanguageCollection as LanguageDtoCollection;
use Contena\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Contena\Core\System\Snippet\Service\TranslationLoader;
use Contena\Core\System\Snippet\SnippetDefinition;
use Contena\Core\System\Snippet\Struct\TranslationConfig;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Tests\Unit\Core\System\Snippet\Mock\TestPlugin;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[CoversClass(SnippetFinder::class)]
class SnippetFinderTest extends TestCase
{
    use SnippetFileTrait;

    private Filesystem $filesystem;

    /**
     * @var StaticEntityRepository<LanguageCollection>
     */
    private StaticEntityRepository $languageRepository;

    /**
     * @var StaticEntityRepository<LocaleCollection>
     */
    private StaticEntityRepository $localeRepository;

    /**
     * @var StaticEntityRepository<SnippetSetCollection>
     */
    private StaticEntityRepository $snippetSetRepository;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->languageRepository = new StaticEntityRepository([], new LanguageDefinition());
        $this->localeRepository = new StaticEntityRepository([], new LocaleDefinition());
        $this->snippetSetRepository = new StaticEntityRepository([], new SnippetDefinition());
    }

    public function testNoSnippetsFound(): void
    {
        $snippetFinder = $this->getSnippetFinder();

        static::assertEmpty($snippetFinder->findSnippets('fr-FR'));
    }

    public function testDefaultSnippetFileLoading(): void
    {
        $activePluginPaths = [
            'activePlugin',
            'invalidPlugin',
            'nonExistingPlugin',
        ];
        $pluginPaths = [
            'activePlugin',
            'irrelevantPlugin',
        ];
        $bundlePaths = [
            'Administration',
            'existingBundle',
            'nonExistingBundle',
        ];

        $snippetFinder = $this->getSnippetFinder(
            $this->getKernelMock($pluginPaths, $activePluginPaths, $bundlePaths)
        );

        $actualSnippets = $snippetFinder->findSnippets('jp-JP');

        static::assertEquals([
            'activePlugin' => 'successfully loaded',
            'existingBundle' => 'successfully loaded as well',
            'activeMeteorApp' => 'Snippet',
            'existingBundleMeteorApp' => 'Loaded from a bundle',
        ], $actualSnippets);
    }

    /**
     * @param list<string> $pluginPaths
     * @param list<string> $activePluginPaths
     * @param list<string> $bundlePaths
     */
    public function getKernelMock(
        array $pluginPaths = [],
        array $activePluginPaths = [],
        array $bundlePaths = []
    ): Kernel&Stub {
        $getBundleMockByPath = static function (string $path): Plugin {
            $path = __DIR__ . '/fixtures/' . $path;

            $plugin = new TestPlugin(true, $path);
            $plugin->setName('activePlugin');
            $plugin->setPath($path);

            return $plugin;
        };

        $plugins = array_map($getBundleMockByPath, $pluginPaths);
        $activePlugins = array_map($getBundleMockByPath, $activePluginPaths);

        $adminBundle = static::createStub(Administration::class);

        $adminBundleFileName = new \ReflectionClass(Administration::class)->getFileName();
        static::assertNotFalse($adminBundleFileName);

        $adminBundle
            ->method('getPath')
            ->willReturn(\dirname($adminBundleFileName));

        $property = new \ReflectionProperty(Administration::class, 'name');
        $property->setValue($adminBundle, 'Administration');

        $bundles = [
            ...array_map($getBundleMockByPath, $bundlePaths),
            ...$plugins,
            $adminBundle,
        ];

        $pluginCollectionMock = static::createStub(KernelPluginCollection::class);
        $pluginCollectionMock
            ->method('all')
            ->willReturn($plugins);
        $pluginCollectionMock
            ->method('getActives')
            ->willReturn($activePlugins);

        $pluginLoaderMock = static::createStub(KernelPluginLoader::class);
        $pluginLoaderMock
            ->method('getPluginInstances')
            ->willReturn($pluginCollectionMock);

        $kernelMock = static::createStub(Kernel::class);
        $kernelMock
            ->method('getPluginLoader')
            ->willReturn($pluginLoaderMock);
        $kernelMock
            ->method('getBundles')
            ->willReturn($bundles);

        return $kernelMock;
    }

    public function testFindInstalledSnippetsWithoutPluginsActive(): void
    {
        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            [],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['de-DE'],
        );
        $loader = $this->getTranslationLoader($config);

        $this->createSnippetFixtures($this->filesystem, $loader);

        $snippetFinder = $this->getSnippetFinder(
            translationConfig: $config,
        );

        $snippets = $snippetFinder->findSnippets('es-ES');

        static::assertEquals(['system_administration' => 'Platform admin'], $snippets);
    }

    public function testFindInstalledSnippetsWithActivePlugin(): void
    {
        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            ['activePlugin'],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['de-DE'],
        );
        $loader = $this->getTranslationLoader($config);
        $this->createSnippetFixtures($this->filesystem, $loader);

        $pluginPath = __DIR__ . '/_fixtures/activePlugin';
        $snippetFinder = $this->getSnippetFinder(
            kernel: $this->getKernelMock(pluginPaths: [$pluginPath], activePluginPaths: ['activePlugin']),
            translationConfig: $config,
        );

        $snippets = $snippetFinder->findSnippets('es-ES');

        static::assertEquals([
            'plugin_administration' => 'Plugin admin',
            'system_administration' => 'Platform admin',
        ], $snippets);
    }

    #[TestDox('An invalid snippet file is skipped and logged instead of breaking the administration')]
    public function testInvalidSnippetFileIsSkippedAndLogged(): void
    {
        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            ['activePlugin'],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['de-DE'],
        );
        $loader = $this->getTranslationLoader($config);
        $this->createSnippetFixtures($this->filesystem, $loader);

        $invalidFilePath = Path::join($loader->getLocalePath('es-ES'), 'Platform', 'administration.json');
        $this->filesystem->write($invalidFilePath, '{');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->willReturnCallback(function (string $message) use ($invalidFilePath): void {
                $this->assertStringContainsString($invalidFilePath, $message);
            });

        $snippetFinder = $this->getSnippetFinder(
            kernel: $this->getKernelMock(pluginPaths: ['activePlugin'], activePluginPaths: ['activePlugin']),
            translationConfig: $config,
            logger: $logger,
        );

        static::assertSame(
            ['plugin_administration' => 'Plugin admin'],
            $snippetFinder->findSnippets('es-ES'),
            'snippets of intact files must survive an invalid file'
        );
    }

    #[TestDox('An empty snippet file is skipped without logging an error')]
    public function testEmptySnippetFileIsSkipped(): void
    {
        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            ['activePlugin'],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['de-DE'],
        );
        $loader = $this->getTranslationLoader($config);
        $this->createSnippetFixtures($this->filesystem, $loader);

        $emptyFilePath = Path::join($loader->getLocalePath('es-ES'), 'Platform', 'administration.json');
        $this->filesystem->write($emptyFilePath, '');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->never())
            ->method('error');

        $snippetFinder = $this->getSnippetFinder(
            kernel: $this->getKernelMock(pluginPaths: ['activePlugin'], activePluginPaths: ['activePlugin']),
            translationConfig: $config,
            logger: $logger,
        );

        static::assertSame(
            ['plugin_administration' => 'Plugin admin'],
            $snippetFinder->findSnippets('es-ES'),
            'snippets of intact files must survive an empty file'
        );
    }

    #[TestDox('In debug mode an invalid snippet file throws an exception naming the file')]
    public function testInvalidSnippetFileThrowsInDebugMode(): void
    {
        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            [],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['de-DE'],
        );
        $loader = $this->getTranslationLoader($config);
        $this->createSnippetFixtures($this->filesystem, $loader);

        $invalidFilePath = Path::join($loader->getLocalePath('es-ES'), 'Platform', 'administration.json');
        $this->filesystem->write($invalidFilePath, '{');

        $snippetFinder = $this->getSnippetFinder(
            translationConfig: $config,
            debug: true,
        );

        $this->expectExceptionObject(SnippetException::invalidSnippetFile($invalidFilePath, new \JsonException('Syntax error')));

        $snippetFinder->findSnippets('es-ES');
    }

    public function testFinderSkipsExcludedLocales(): void
    {
        $config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            ['activePlugin'],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['es-ES'],
        );
        $loader = $this->getTranslationLoader($config);
        $this->createSnippetFixtures($this->filesystem, $loader);

        $pluginPath = __DIR__ . '/_fixtures/activePlugin';
        $snippetFinder = $this->getSnippetFinder(
            kernel: $this->getKernelMock(pluginPaths: [$pluginPath], activePluginPaths: ['activePlugin']),
            translationConfig: $config,
        );

        $snippets = $snippetFinder->findSnippets('es-ES');
        static::assertEmpty($snippets);
    }

    private function getSnippetFinder(
        (Kernel&Stub)|null $kernel = null,
        ?TranslationConfig $translationConfig = null,
        ?LoggerInterface $logger = null,
        bool $debug = false,
    ): SnippetFinder {
        $config = $translationConfig ?? new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['en-GB'],
            [],
            new LanguageDtoCollection([new LanguageDto('en-GB', 'English (UK')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['de-DE'],
        );

        $kernelMock = $kernel ?? $this->getKernelMock();
        $translationLoader = $this->getTranslationLoader($config);

        return new SnippetFinder(
            $kernelMock,
            $this->filesystem,
            $config,
            $translationLoader,
            $logger ?? new NullLogger(),
            $debug,
        );
    }

    private function getTranslationLoader(
        TranslationConfig $translationConfig,
    ): TranslationLoader {
        return new TranslationLoader(
            translationWriter: $this->filesystem,
            languageRepository: $this->languageRepository,
            localeRepository: $this->localeRepository,
            snippetSetRepository: $this->snippetSetRepository,
            client: static::createStub(ClientInterface::class),
            config: $translationConfig,
            eventDispatcher: new EventDispatcher(),
        );
    }
}
