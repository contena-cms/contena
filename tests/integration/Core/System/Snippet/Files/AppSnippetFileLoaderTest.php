<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Snippet\Files;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\App\Source\SourceResolver;
use Contena\Core\Kernel;
use Contena\Core\System\Snippet\Files\AppSnippetFileLoader;
use Contena\Core\System\Snippet\Files\FrontendSnippetStorage;
use Contena\Core\System\Snippet\Files\SnippetFileCollection;
use Contena\Core\System\Snippet\Files\SnippetFileLoader;
use Contena\Core\System\Snippet\Service\TranslationLoader;
use Contena\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Filesystem as Io;

/**
 * @internal
 */
class AppSnippetFileLoaderTest extends TestCase
{
    use AppSystemTestBehaviour;
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private SnippetFileLoader $snippetFileLoader;

    private string $mirrorDirectory;

    protected function setUp(): void
    {
        $flySystem = new Flysystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);
        $this->mirrorDirectory = sys_get_temp_dir() . '/' . uniqid('contena-app-snippet-mirror-', true);
        $this->snippetFileLoader = new SnippetFileLoader(
            static::createStub(Kernel::class),
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(AppSnippetFileLoader::class),
            static::getContainer()->get(ActiveAppsLoader::class),
            static::getContainer()->get(TranslationConfig::class),
            static::getContainer()->get(TranslationLoader::class),
            $flySystem,
            new FrontendSnippetStorage($flySystem, static::getContainer()->get(SourceResolver::class), new NullLogger(), $this->mirrorDirectory),
            $flySystem,
        );
    }

    protected function tearDown(): void
    {
        (new Io())->remove($this->mirrorDirectory);
    }

    public function testLoadSnippetFilesIntoCollectionWithoutSnippetFiles(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/Apps/AppWithoutSnippets');

        $collection = new SnippetFileCollection();

        $this->snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(0, $collection);
    }

    public function testLoadSnippetFilesIntoCollection(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/Apps/AppWithSnippets');

        $collection = new SnippetFileCollection();

        $this->snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('de')[0];
        static::assertSame('frontend.de', $snippetFile->getName());
        static::assertStringStartsWith($this->mirrorDirectory . '/', $snippetFile->getPath());
        static::assertFileEquals(
            __DIR__ . '/_fixtures/Apps/AppWithSnippets/Resources/snippet/frontend.de.json',
            $snippetFile->getPath()
        );
        static::assertSame('de', $snippetFile->getIso());
        static::assertSame('Contena', $snippetFile->getAuthor());
        static::assertFalse($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('en')[0];
        static::assertSame('frontend.en', $snippetFile->getName());
        static::assertStringStartsWith($this->mirrorDirectory . '/', $snippetFile->getPath());
        static::assertFileEquals(
            __DIR__ . '/_fixtures/Apps/AppWithSnippets/Resources/snippet/frontend.en.json',
            $snippetFile->getPath()
        );
        static::assertSame('en', $snippetFile->getIso());
        static::assertSame('Contena', $snippetFile->getAuthor());
        static::assertFalse($snippetFile->isBase());
    }

    public function testLoadSnippetFilesDoesNotLoadSnippetsFromInactiveApps(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/Apps/AppWithSnippets', false);

        $collection = new SnippetFileCollection();

        $this->snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(0, $collection);
    }

    public function testLoadBaseSnippetFilesIntoCollection(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/Apps/AppWithBaseSnippets');

        $collection = new SnippetFileCollection();

        $this->snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('de')[0];
        static::assertSame('frontend.de', $snippetFile->getName());
        static::assertStringStartsWith($this->mirrorDirectory . '/', $snippetFile->getPath());
        static::assertFileEquals(
            __DIR__ . '/_fixtures/Apps/AppWithBaseSnippets/Resources/snippet/frontend.de.base.json',
            $snippetFile->getPath()
        );
        static::assertSame('de', $snippetFile->getIso());
        static::assertSame('Contena', $snippetFile->getAuthor());
        static::assertTrue($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('en')[0];
        static::assertSame('frontend.en', $snippetFile->getName());
        static::assertStringStartsWith($this->mirrorDirectory . '/', $snippetFile->getPath());
        static::assertFileEquals(
            __DIR__ . '/_fixtures/Apps/AppWithBaseSnippets/Resources/snippet/frontend.en.base.json',
            $snippetFile->getPath()
        );
        static::assertSame('en', $snippetFile->getIso());
        static::assertSame('Contena', $snippetFile->getAuthor());
        static::assertTrue($snippetFile->isBase());
    }

    public function testLoadSnippetFilesIntoCollectionIgnoresWrongFilenames(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/Apps/SnippetsWithWrongName');

        $collection = new SnippetFileCollection();

        $this->snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(0, $collection);
    }
}
