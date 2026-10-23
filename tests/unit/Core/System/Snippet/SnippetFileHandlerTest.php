<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Snippet\SnippetFileHandler;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(SnippetFileHandler::class)]
class SnippetFileHandlerTest extends TestCase
{
    public function testFindsTheSnippetFilesOfAnExtensionBelowADirectory(): void
    {
        $handler = new SnippetFileHandler(new Filesystem());
        $extension = __DIR__ . '/_fixtures/ExtensionWithSnippets';
        $bundle = $extension . '/src/Sample/Resources';

        static::assertSame(
            [
                $bundle . '/app/administration/src/module/sample/snippet/de.json',
                $bundle . '/app/administration/src/module/sample/snippet/en.json',
            ],
            $handler->findAdministrationSnippetFilesBelow($extension),
        );
        static::assertSame(
            [
                $bundle . '/snippet/frontend.de.json',
                $bundle . '/snippet/frontend.en.json',
            ],
            $handler->findFrontendSnippetFilesBelow($extension),
        );
    }

    public function testFindsTheAdministrationSnippetFilesOfAnAppBelowADirectory(): void
    {
        $handler = new SnippetFileHandler(new Filesystem());
        $app = __DIR__ . '/_fixtures/AppWithSnippets';

        static::assertSame(
            [
                $app . '/Resources/app/administration/snippet/de.json',
                $app . '/Resources/app/administration/snippet/en.json',
            ],
            $handler->findAdministrationSnippetFilesBelow($app),
        );
        static::assertSame([], $handler->findFrontendSnippetFilesBelow($app));
    }

    public function testExistsReflectsTheFilesystem(): void
    {
        $handler = new SnippetFileHandler(new Filesystem());

        static::assertTrue($handler->exists(__FILE__));
        static::assertFalse($handler->exists(__DIR__ . '/does-not-exist.json'));
    }
}
