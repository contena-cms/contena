<?php declare(strict_types=1);

namespace Contena\Core\Content\Test\Media;

use PHPUnit\Framework\Attributes\Before;
use Contena\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Contena\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Media\MediaType\BinaryType;
use Contena\Core\Content\Media\MediaType\DocumentType;
use Contena\Core\Content\Media\MediaType\ImageType;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Integration\Traits\EntityFixturesBase;

/**
 * @internal
 */
trait MediaFixtures
{
    use EntityFixturesBase;

    /**
     * @phpstan-var array<string, array<string, mixed>>
     */
    // PHPStan loses trait property PHPDoc while analysing consuming test classes.
    // @phpstan-ignore missingType.iterableValue
    public array $mediaFixtures;

    public string $thumbnailSize150Id;

    public string $thumbnailSize200Id;

    public string $thumbnailSize300Id;

    #[Before]
    public function initializeMediaFixtures(): void
    {
        $this->thumbnailSize200Id = $this->getOrCreateThumbnailSizeId(200, 200);

        $this->mediaFixtures = [
            'NamedEmpty' => [
                'id' => Uuid::randomHex(),
            ],

            'NamedMimePng' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/png',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],
            'NamedMimePngEtxPng' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => 'pngFileWithExtension',
                'path' => 'media/_test/pngFileWithExtension.png',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],
            'NamedMimeTxtEtxTxt' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'plain/txt',
                'fileExtension' => 'txt',
                'fileName' => 'textFileWithExtension',
                'path' => 'media/_test/textFileWithExtension.txt',
                'fileSize' => 1024,
                'mediaType' => new BinaryType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],
            'NamedMimeJpgEtxJpg' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileName' => 'jpgFileWithExtension',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],
            'NamedMimePdfEtxPdf' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'application/pdf',
                'fileExtension' => 'pdf',
                'fileName' => 'pdfFileWithExtension',
                'fileSize' => 1024,
                'mediaType' => new DocumentType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
            ],
            'NamedWithThumbnail' => [
                'id' => Uuid::randomHex(),
                'thumbnails' => [[
                    'width' => 200,
                    'height' => 200,
                    'highDpi' => false,
                    'mediaThumbnailSizeId' => $this->thumbnailSize200Id,
                ]],
            ],
            'NamedMimePngEtxPngWithFolder' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => 'pngFileWithExtensionAndFolder',
                'fileSize' => 1024,
                'path' => 'media/_test/pngFileWithExtensionAndFolder.png',
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                'mediaFolder' => [],
            ],
            'NamedMimeJpgEtxJpgWithFolder' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileName' => 'jpgFileWithExtensionAndFolder',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                'mediaFolder' => [],
            ],
            'NamedMimeJpgEtxJpgWithFolderWithoutThumbnails' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/jpg',
                'fileExtension' => 'jpg',
                'fileName' => 'jpgFileWithExtensionAndCatalog',
                'fileSize' => 1024,
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                'mediaFolder' => [],
            ],
            'NamedMimePngEtxPngWithFolderHugeThumbnails' => [
                'id' => Uuid::randomHex(),
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => 'pngFileWithExtensionAndFolder',
                'fileSize' => 1024,
                'path' => 'media/_test/pngFileWithExtensionAndFolder.png',
                'mediaType' => new ImageType(),
                'uploadedAt' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                'mediaFolder' => [],
            ],
        ];
    }

    public function getEmptyMedia(): MediaEntity
    {
        return $this->getMediaFixture('NamedEmpty');
    }

    public function getPngWithoutExtension(): MediaEntity
    {
        return $this->getMediaFixture('NamedMimePng');
    }

    public function getPng(): MediaEntity
    {
        return $this->getMediaFixture('NamedMimePngEtxPng');
    }

    public function getTxt(): MediaEntity
    {
        return $this->getMediaFixture('NamedMimeTxtEtxTxt');
    }

    public function getJpg(): MediaEntity
    {
        return $this->getMediaFixture('NamedMimeJpgEtxJpg');
    }

    public function getPdf(): MediaEntity
    {
        return $this->getMediaFixture('NamedMimePdfEtxPdf');
    }

    public function getMediaWithThumbnail(): MediaEntity
    {
        $this->mediaFixtures['NamedWithThumbnail']['thumbnails'][0]['mediaThumbnailSizeId'] = $this->thumbnailSize200Id;

        return $this->getMediaFixture('NamedWithThumbnail');
    }

    public function getPngWithFolder(): MediaEntity
    {
        $this->mediaFixtures['NamedMimePngEtxPngWithFolder']['mediaFolder'] = $this->folderFixture(true);

        return $this->getMediaFixture('NamedMimePngEtxPngWithFolder');
    }

    public function getPngWithFolderHugeThumbnails(): MediaEntity
    {
        $this->mediaFixtures['NamedMimePngEtxPngWithFolderHugeThumbnails']['mediaFolder'] = $this->folderFixture(true, true);

        return $this->getMediaFixture('NamedMimePngEtxPngWithFolderHugeThumbnails');
    }

    public function getJpgWithFolder(): MediaEntity
    {
        $this->mediaFixtures['NamedMimeJpgEtxJpgWithFolder']['mediaFolder'] = $this->folderFixture(true);

        return $this->getMediaFixture('NamedMimeJpgEtxJpgWithFolder');
    }

    public function getJpgWithFolderWithoutThumbnails(): MediaEntity
    {
        $this->mediaFixtures['NamedMimeJpgEtxJpgWithFolderWithoutThumbnails']['mediaFolder'] = $this->folderFixture(false);

        return $this->getMediaFixture('NamedMimeJpgEtxJpgWithFolderWithoutThumbnails');
    }

    public function setFixtureContext(Context $context): void
    {
        $this->entityFixtureContext = $context;
        $this->thumbnailSize200Id = $this->getOrCreateThumbnailSizeId(200, 200);
    }

    protected function getOrCreateThumbnailSizeId(int $width, int $height): string
    {
        /** @var EntityRepository<MediaThumbnailSizeCollection> $mediaThumbnailSizeRepository */
        $mediaThumbnailSizeRepository = static::getFixtureRepository('media_thumbnail_size');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('width', $width), new EqualsFilter('height', $height));
        $mediaThumbnailSize = $mediaThumbnailSizeRepository->search($criteria, $this->entityFixtureContext)->getEntities()->first();
        if ($mediaThumbnailSize instanceof MediaThumbnailSizeEntity) {
            return $mediaThumbnailSize->getId();
        }

        $thumbnailSizeId = Uuid::randomHex();
        $mediaThumbnailSizeRepository->create([[
            'id' => $thumbnailSizeId,
            'width' => $width,
            'height' => $height,
        ]], $this->entityFixtureContext);

        return $thumbnailSizeId;
    }

    /**
     * @phpstan-return array<string, mixed>
     */
    // PHPStan loses trait method PHPDoc while analysing consuming test classes.
    // @phpstan-ignore missingType.iterableValue
    private function folderFixture(bool $createThumbnails, bool $huge = false): array
    {
        $configuration = ['createThumbnails' => $createThumbnails];

        if ($createThumbnails) {
            $smallSize = $huge ? 1500 : 150;
            $largeSize = $huge ? 3000 : 300;
            $this->thumbnailSize150Id = $this->getOrCreateThumbnailSizeId($smallSize, $smallSize);
            $this->thumbnailSize300Id = $this->getOrCreateThumbnailSizeId($largeSize, $largeSize);
            $configuration += [
                'keepAspectRatio' => true,
                'thumbnailQuality' => 80,
                'mediaThumbnailSizes' => [
                    [
                        'id' => $this->thumbnailSize150Id,
                        'width' => $smallSize,
                        'height' => $smallSize,
                    ],
                    [
                        'id' => $this->thumbnailSize300Id,
                        'width' => $largeSize,
                        'height' => $largeSize,
                    ],
                ],
            ];
        }

        return [
            'name' => 'test folder',
            'useParentConfiguration' => false,
            'configuration' => $configuration,
        ];
    }

    private function getMediaFixture(string $fixtureName): MediaEntity
    {
        $media = $this->createFixture(
            $fixtureName,
            $this->mediaFixtures,
            self::getFixtureRepository('media')
        );

        static::assertInstanceOf(MediaEntity::class, $media);

        return $media;
    }
}
