<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\TypeDetector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\File\MediaFile;
use Contena\Core\Content\Media\MediaType\ImageType;
use Contena\Core\Content\Media\MediaType\SpatialObjectType;
use Contena\Core\Content\Media\TypeDetector\SpatialObjectTypeDetector;

/**
 * @internal
 */
#[CoversClass(SpatialObjectTypeDetector::class)]
class SpatialObjectTypeDetectorTest extends TestCase
{
    /**
     * @var MediaFile&Stub
     */
    private MediaFile $mediaFile;

    protected function setUp(): void
    {
        $this->mediaFile = static::createStub(MediaFile::class);
    }

    public function testDetectWithExtensionGlbWillReturnSpatialObjectType(): void
    {
        $this->mediaFile->method('getFileExtension')->willReturn('glb');
        $detectedType = new SpatialObjectTypeDetector()->detect($this->mediaFile, null);
        static::assertInstanceOf(SpatialObjectType::class, $detectedType);
    }

    public function testDetectWithPreviouslyDetectedTypeButExtensionGlbWillReturnOriginalType(): void
    {
        $this->mediaFile->method('getFileExtension')->willReturn('glb');
        $detectedType = new SpatialObjectTypeDetector()->detect($this->mediaFile, new ImageType());
        static::assertInstanceOf(ImageType::class, $detectedType);
    }

    public function testDetectWithPreviouslyDetectedTypeAndNot3dFileExtensionWillReturnOriginalType(): void
    {
        $this->mediaFile->method('getFileExtension')->willReturn('png');
        $detectedType = new SpatialObjectTypeDetector()->detect($this->mediaFile, new ImageType());
        static::assertInstanceOf(ImageType::class, $detectedType);
    }
}
