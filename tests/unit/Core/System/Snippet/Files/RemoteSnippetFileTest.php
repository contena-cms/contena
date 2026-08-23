<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\Files;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Snippet\Files\RemoteSnippetFile;

/**
 * @internal
 */
#[CoversClass(RemoteSnippetFile::class)]
class RemoteSnippetFileTest extends TestCase
{
    public function testGetters(): void
    {
        $file = new RemoteSnippetFile(
            'frontend.en-GB',
            '/appPath/subDirectory/frontend.en-GB.json',
            'en-GB',
            'contena',
            true,
            'frontend'
        );

        static::assertSame('frontend.en-GB', $file->getName());
        static::assertSame('/appPath/subDirectory/frontend.en-GB.json', $file->getPath());
        static::assertSame('en-GB', $file->getIso());
        static::assertSame('contena', $file->getAuthor());
        static::assertTrue($file->isBase());
        static::assertSame('frontend', $file->getTechnicalName());
    }
}
