<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\Files;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Bundle;
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
use Contena\Core\System\Snippet\Files\GenericSnippetFile;
use Contena\Core\System\Snippet\Files\RemoteSnippetFile;
use Contena\Core\System\Snippet\Files\SnippetFileCollection;
use Contena\Core\System\Snippet\Files\SnippetFileLoader;
use Contena\Core\System\Snippet\Service\TranslationLoader;
use Contena\Core\System\Snippet\SnippetDefinition;
use Contena\Core\System\Snippet\Struct\TranslationConfig;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Tests\Unit\Administration\Snippet\SnippetFileTrait;
use Contena\Tests\Unit\Core\System\Snippet\Files\_fixtures\BaseSnippetSet\BaseSnippetSet;
use Contena\Tests\Unit\Core\System\Snippet\Files\_fixtures\SnippetSet\SnippetSet;
use Contena\Tests\Unit\Core\System\Snippet\Files\_fixtures\ContenaBundleWithSnippets\ContenaBundleWithSnippets;
use Contena\Tests\Unit\Core\System\Snippet\Mock\TestPlugin;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[CoversClass(SnippetFileLoader::class)]
class SnippetFileLoaderTest extends TestCase
{
    use SnippetFileTrait;

    private TranslationConfig $config;

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
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            ['activePlugin'],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['it-IT'],
        );
    }

    public function testLoadSnippetsFromContenaBundle(): void
    {
        $kernel = $this->getKernel([
            'ContenaBundleWithSnippets' => new ContenaBundleWithSnippets(),
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            static::createStub(Connection::class),
            $this->config,
            $this->getTranslationLoader(),
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('de')[0];
        static::assertSame('frontend.de', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/ContenaBundleWithSnippets/Resources/snippet/frontend.de.json',
            $snippetFile->getPath()
        );
        static::assertSame('de', $snippetFile->getIso());
        static::assertSame('Contena', $snippetFile->getAuthor());
        static::assertFalse($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('en')[0];
        static::assertSame('frontend.en', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/ContenaBundleWithSnippets/Resources/snippet/frontend.en.json',
            $snippetFile->getPath()
        );
        static::assertSame('en', $snippetFile->getIso());
        static::assertSame('Contena', $snippetFile->getAuthor());
        static::assertSame('ContenaBundleWithSnippets', $snippetFile->getTechnicalName());
        static::assertFalse($snippetFile->isBase());
    }

    public function testLoadSnippetFilesIntoCollectionDoesNotOverwriteFiles(): void
    {
        $kernel = $this->getKernel([
            'ContenaBundleWithSnippets' => new ContenaBundleWithSnippets(),
        ]);

        $collection = new SnippetFileCollection([
            new GenericSnippetFile(
                'test',
                __DIR__ . '/_fixtures/ContenaBundleWithSnippets/Resources/snippet/frontend.de.json',
                'xx-XX',
                'test Author',
                true,
                'ContenaBundleWithSnippets',
            ),
            new GenericSnippetFile(
                'test',
                __DIR__ . '/_fixtures/ContenaBundleWithSnippets/Resources/snippet/frontend.en.json',
                'yy-YY',
                'test Author',
                true,
                'ContenaBundleWithSnippets',
            ),
        ]);

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            static::createStub(Connection::class),
            $this->config,
            $this->getTranslationLoader(),
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('xx-XX')[0];
        static::assertSame('test', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/ContenaBundleWithSnippets/Resources/snippet/frontend.de.json',
            $snippetFile->getPath()
        );
        static::assertSame('xx-XX', $snippetFile->getIso());
        static::assertSame('test Author', $snippetFile->getAuthor());
        static::assertTrue($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('yy-YY')[0];
        static::assertSame('test', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/ContenaBundleWithSnippets/Resources/snippet/frontend.en.json',
            $snippetFile->getPath()
        );
        static::assertSame('yy-YY', $snippetFile->getIso());
        static::assertSame('test Author', $snippetFile->getAuthor());
        static::assertSame('ContenaBundleWithSnippets', $snippetFile->getTechnicalName());
        static::assertTrue($snippetFile->isBase());
    }

    public function testLoadSnippetsFromPlugin(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllKeyValue')->willReturn([
            SnippetSet::class => 'Plugin Manufacturer',
        ]);

        $kernel = $this->getKernel([
            'SnippetSet' => new SnippetSet(true, __DIR__),
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $connection,
            $this->config,
            $this->getTranslationLoader(),
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('de')[0];
        static::assertSame('frontend.de', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/SnippetSet/Resources/snippet/frontend.de.json',
            $snippetFile->getPath()
        );
        static::assertSame('de', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertFalse($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('en')[0];
        static::assertSame('frontend.en', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/SnippetSet/Resources/snippet/frontend.en.json',
            $snippetFile->getPath()
        );
        static::assertSame('en', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertSame('SnippetSet', $snippetFile->getTechnicalName());
        static::assertFalse($snippetFile->isBase());
    }

    public function testLoadBaseSnippetsFromPlugin(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllKeyValue')->willReturn([
            BaseSnippetSet::class => 'Plugin Manufacturer',
        ]);

        $kernel = $this->getKernel([
            'BaseSnippetSet' => new BaseSnippetSet(true, __DIR__),
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $connection,
            $this->config,
            $this->getTranslationLoader(),
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('de')[0];
        static::assertSame('frontend.de', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/BaseSnippetSet/Resources/snippet/frontend.de.base.json',
            $snippetFile->getPath()
        );
        static::assertSame('de', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertSame('BaseSnippetSet', $snippetFile->getTechnicalName());
        static::assertTrue($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('en')[0];
        static::assertSame('frontend.en', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/BaseSnippetSet/Resources/snippet/frontend.en.base.json',
            $snippetFile->getPath()
        );
        static::assertSame('en', $snippetFile->getIso());
        static::assertSame('Plugin Manufacturer', $snippetFile->getAuthor());
        static::assertTrue($snippetFile->isBase());
    }

    public function testLoadInstalledCoreAndPluginSnippets(): void
    {
        $loader = $this->getTranslationLoader();
        $this->createSnippetFixtures($this->filesystem, $loader);

        $path = __DIR__ . '/_fixtures/activePlugin';

        $plugin = new TestPlugin(true, $path);
        $plugin->setName('activePlugin');
        $plugin->setPath($path);

        $kernel = $this->getKernel([], $plugin);
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            ['activePlugin', 'inactivePlugin'],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['it-IT'],
        );

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            static::createStub(Connection::class),
            $this->config,
            $loader,
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);
        static::assertCount(6, $collection);

        $files = $collection->getElements();
        static::assertContainsOnlyInstancesOf(RemoteSnippetFile::class, $files);

        $platformPath = Path::join($loader->getLocalePath('es-ES'), 'Platform');
        $platformPath = mb_ltrim($platformPath, '/\\');
        $activePluginPath = Path::join($loader->getLocalePath('es-ES'), 'Plugins', 'activePlugin');
        $activePluginPath = mb_ltrim($activePluginPath, '/\\');
        $actualPaths = array_map(static fn (RemoteSnippetFile $file) => $file->getPath(), $files);

        $expectedPaths = [
            Path::join($platformPath, 'frontend.json'),
            Path::join($platformPath, 'messages.es-ES.base.json'),
            Path::join($platformPath, 'administration.json'),
            Path::join($activePluginPath, 'frontend.json'),
            Path::join($activePluginPath, 'messages.es-ES.base.json'),
            Path::join($activePluginPath, 'administration.json'),
        ];

        sort($actualPaths);
        sort($expectedPaths);

        static::assertSame($expectedPaths, $actualPaths);
    }

    public function testLoadLegacySnippetsHandlesDatabaseException(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willThrowException(new QueryException('Query failed'));

        $kernel = $this->getKernel([
            'ContenaBundleWithSnippets' => new ContenaBundleWithSnippets(),
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $connection,
            $this->config,
            $this->getTranslationLoader(),
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        // Verify author falls back to 'Contena' for bundles when DB fails
        $snippetFile = $collection->getSnippetFilesByIso('de')[0];
        static::assertSame('Contena', $snippetFile->getAuthor());
    }

    public function testLoadLegacySnippetsSkipsNonBundleObjects(): void
    {
        $kernel = static::createStub(Kernel::class);
        $kernel->method('getBundles')->willReturn([
            'NonBundle' => new \stdClass(),
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            static::createStub(Connection::class),
            $this->config,
            $this->getTranslationLoader(),
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(0, $collection);
    }

    public function testLoadLegacySnippetsSkipsAdministrationBundle(): void
    {
        $plugin = new TestPlugin(true, '');
        $plugin->setPath('/fake/admin/path');
        $plugin->setName('TestPlugin');

        $loader = $this->getTranslationLoader();

        $pluginPath = Path::join($loader->getLocalePath('es-ES'), 'Plugins', $plugin->getName());
        $this->filesystem->createDirectory($pluginPath);

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getBundles')->willReturn([
            $plugin->getName() => $plugin,
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            static::createStub(Connection::class),
            $this->config,
            $loader,
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(0, $collection);
    }

    public function testLoadSkipsExcludedLocales(): void
    {
        $loader = $this->getTranslationLoader();
        $this->createSnippetFixtures($this->filesystem, $loader);

        $path = __DIR__ . '/_fixtures/activePlugin';

        $plugin = new TestPlugin(true, $path);
        $plugin->setName('activePlugin');
        $plugin->setPath($path);

        $kernel = $this->getKernel([], $plugin);
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['es-ES'],
            ['activePlugin', 'inactivePlugin'],
            new LanguageDtoCollection([new LanguageDto('es-ES', 'Español')]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            ['es-ES'],
        );

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            static::createStub(Connection::class),
            $this->config,
            $loader,
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        $files = $collection->getElements();
        static::assertEmpty($files);
    }

    public function testLoadShippedSnippetsSkipsLocalFileOnlyForLocaleWithCoreTranslation(): void
    {
        $loader = $this->getTranslationLoader();

        // Simulate a core translation installed only for locale 'de' (not 'en')
        $this->filesystem->createDirectory($loader->getLocalePath('de') . '/Plugins/SnippetSet');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllKeyValue')->willReturn([
            SnippetSet::class => 'Plugin Manufacturer',
        ]);

        $kernel = $this->getKernel([
            'SnippetSet' => new SnippetSet(true, __DIR__),
        ]);

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            $kernel,
            $connection,
            $this->config,
            $loader,
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        // Only the 'en' file should be loaded; 'de' is skipped because a core translation exists for that locale
        static::assertCount(1, $collection);
        $snippetFile = $collection->getSnippetFilesByIso('en')[0];
        static::assertSame('frontend.en', $snippetFile->getName());
        static::assertSame('en', $snippetFile->getIso());
    }

    public function testLoadCoreSnippetsSkipsInvalidPathStructure(): void
    {
        $this->filesystem->write('locales/invalid-path/file.json', '{}');

        $translationLoader = static::createStub(TranslationLoader::class);
        $translationLoader->method('getLocalesBasePath')->willReturn('locales');

        $collection = new SnippetFileCollection();

        $snippetFileLoader = new SnippetFileLoader(
            static::createStub(Kernel::class),
            static::createStub(Connection::class),
            $this->config,
            $translationLoader,
            $this->filesystem
        );

        $snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        // Should be empty because the invalid path structure was skipped
        static::assertCount(0, $collection);
    }

    /**
     * @param array<string, Bundle> $bundles
     */
    private function getKernel(array $bundles, ?Plugin $plugin = null): MockedKernel
    {
        $pluginCollection = new KernelPluginCollection();

        if ($plugin) {
            $pluginCollection->add($plugin);
        }

        $pluginLoader = static::createStub(KernelPluginLoader::class);
        $pluginLoader->method('getPluginInstances')->willReturn($pluginCollection);

        return new MockedKernel($bundles, $pluginLoader);
    }

    private function getTranslationLoader(): TranslationLoader
    {
        return new TranslationLoader(
            translationWriter: $this->filesystem,
            languageRepository: $this->languageRepository,
            localeRepository: $this->localeRepository,
            snippetSetRepository: $this->snippetSetRepository,
            client: static::createStub(ClientInterface::class),
            config: $this->config,
            eventDispatcher: new EventDispatcher(),
        );
    }
}
