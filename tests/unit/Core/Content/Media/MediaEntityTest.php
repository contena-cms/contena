<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaEntity;

/**
 * @internal
 */
#[CoversClass(MediaEntity::class)]
class MediaEntityTest extends TestCase
{
    #[DataProvider('filenameExtensionProvider')]
    public function testGetFilenameIncludingExtension(?string $file, ?string $ext, ?string $expected): void
    {
        $media = new MediaEntity();

        if ($file) {
            $media->setFileName($file);
        }

        if ($ext) {
            $media->setFileExtension($ext);
        }

        static::assertSame($expected, $media->getFileNameIncludingExtension());
    }

    /**
     * @return iterable<string, array{file: ?string, ext: ?string, expected: ?string}>
     */
    public static function filenameExtensionProvider(): iterable
    {
        yield 'only-ext' => ['file' => null, 'ext' => 'jpg', 'expected' => null];
        yield 'only-file' => ['file' => 'Tuscany-Landscape', 'ext' => null, 'expected' => null];
        yield 'file-and-ext' => ['file' => 'Tuscany-Landscape', 'ext' => 'jpg', 'expected' => 'Tuscany-Landscape.jpg'];
    }
}
