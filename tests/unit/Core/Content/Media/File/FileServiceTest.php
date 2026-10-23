<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\File\FileService;

/**
 * @internal
 */
#[CoversClass(FileService::class)]
class FileServiceTest extends TestCase
{
    #[DataProvider('urlDataProvider')]
    public function testIsUrl(string $url, bool $expectedResult): void
    {
        $fileService = new FileService();

        static::assertSame($expectedResult, $fileService->isUrl($url));
    }

    public static function urlDataProvider(): \Generator
    {
        yield 'http protocol' => ['http://example.com', true];
        yield 'https protocol' => ['https://example.com', true];
        yield 'ftp protocol' => ['ftp://example.com', true];
        yield 'sftp protocol' => ['sftp://example.com', true];
        yield 'unsupported protocol' => ['file://example.com', false];
        yield 'no protocol' => ['example.com', false];
        yield 'no url' => ['no url', false];
    }
}
