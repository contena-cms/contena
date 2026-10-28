<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Cms;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Cms\DefaultMediaResolver;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DefaultMediaResolver::class)]
class DefaultMediaResolverTest extends TestCase
{
    private FilesystemOperator&Stub $filesystem;

    private DefaultMediaResolver $mediaResolver;

    protected function setUp(): void
    {
        $this->filesystem = static::createStub(FilesystemOperator::class);
        $this->mediaResolver = new DefaultMediaResolver($this->filesystem);
    }

    public function testGetDecoratedThrowsException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->mediaResolver->getDecorated();
    }

    public function testGetDefaultCmsMediaEntityNoFile(): void
    {
        $this->filesystem->method('fileExists')
            ->willReturn(false);

        $result = $this->mediaResolver->getDefaultCmsMediaEntity('bundles/storefront/assets/default/cms/nonexistent.jpg');

        static::assertNull($result);
    }

    public function testGetDefaultCmsMediaEntityMimeTypeIsMissing(): void
    {
        $this->filesystem->method('fileExists')
            ->willReturn(true);

        $this->filesystem->method('mimeType')
            ->willReturn('');

        $result = $this->mediaResolver->getDefaultCmsMediaEntity('bundles/storefront/assets/default/cms/contena.jpg');

        static::assertNull($result);
    }

    public function testGetDefaultCmsMediaEntityMissingExtension(): void
    {
        $this->filesystem->method('fileExists')
            ->willReturn(true);

        $this->filesystem->method('mimeType')
            ->willReturn('image/jpeg');

        $this->filesystem->method('mimeType')
            ->willReturnCallback(static function ($filePath) {
                return $filePath === 'bundles/storefront/assets/default/cms/contena' ? 'image/jpeg' : null;
            });

        $result = $this->mediaResolver->getDefaultCmsMediaEntity('bundles/storefront/assets/default/cms/contena');

        static::assertNull($result);
    }

    public function testGetDefaultCmsMediaEntityValidFile(): void
    {
        $this->filesystem->method('fileExists')
            ->willReturn(true);

        $this->filesystem->method('mimeType')
            ->willReturn('image/jpeg');

        $result = $this->mediaResolver->getDefaultCmsMediaEntity('bundles/storefront/assets/default/cms/contena.jpg');

        static::assertInstanceOf(MediaEntity::class, $result);
        static::assertSame('contena', $result->getFileName());
        static::assertSame('image/jpeg', $result->getMimeType());
        static::assertSame('jpg', $result->getFileExtension());
    }
}
