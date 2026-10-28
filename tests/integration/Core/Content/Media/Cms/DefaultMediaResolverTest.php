<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media\Cms;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Cms\DefaultMediaResolver;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class DefaultMediaResolverTest extends TestCase
{
    use IntegrationTestBehaviour;


    private FilesystemOperator $publicFilesystem;

    protected function setUp(): void
    {
        $this->publicFilesystem = $this->getPublicFilesystem();
        $this->mediaResolver = new DefaultMediaResolver($this->publicFilesystem);
    }

    public function testGetDefaultMediaEntityWithoutValidFileName(): void
    {
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('this/file/does/not/exists');

        static::assertNull($media);
    }

    public function testGetDefaultMediaEntityWithValidFileName(): void
    {
        $this->publicFilesystem->write('/bundles/core/assets/default/cms/contena.jpg', '');
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('bundles/core/assets/default/cms/contena.jpg');

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame('contena', $media->getFileName());
        static::assertSame('image/jpeg', $media->getMimeType());
        static::assertSame('jpg', $media->getFileExtension());
    }
}
