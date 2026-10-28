<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\Files;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Snippet\Files\RemoteSnippetFile;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(RemoteSnippetFile::class)]
class RemoteSnippetFileTest extends TestCase
{
    public function testGetters(): void
    {
        $file = new RemoteSnippetFile(
            'storefront.en-GB',
            '/appPath/subDirectory/storefront.en-GB.json',
            'en-GB',
            'contena',
            true,
            'storefront'
        );

        static::assertSame('storefront.en-GB', $file->getName());
        static::assertSame('/appPath/subDirectory/storefront.en-GB.json', $file->getPath());
        static::assertSame('en-GB', $file->getIso());
        static::assertSame('contena', $file->getAuthor());
        static::assertTrue($file->isBase());
        static::assertSame('storefront', $file->getTechnicalName());
    }
}
