<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media\File;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\File\FileFetcher;
use Contena\Core\Content\Media\File\FileLoader;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Test\Media\MediaFixtures;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
final class FileLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;

    public const string TEST_IMAGE = __DIR__ . '/../fixtures/contena-logo.png';

    private FileLoader $fileLoader;

    private FileFetcher $fileFetcher;

    private FileSaver $fileSaver;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    protected function setUp(): void
    {
        $this->fileLoader = static::getContainer()->get(FileLoader::class);
        $this->fileFetcher = static::getContainer()->get(FileFetcher::class);
        $this->fileSaver = static::getContainer()->get(FileSaver::class);
        $this->mediaRepository = static::getContainer()->get('media.repository');
    }

    public function testLoadMediaFile(): void
    {
        $context = Context::createDefaultContext();
        $blob = \file_get_contents(self::TEST_IMAGE);
        static::assertIsString($blob);
        $mediaFile = $this->fileFetcher->fetchBlob($blob, 'png', 'image/png');
        $mediaId = Uuid::randomHex();
        $this->mediaRepository->create([['id' => $mediaId]], $context);
        $this->fileSaver->persistFileToMedia($mediaFile, $mediaId . '.png', $mediaId, $context);
        $this->fileFetcher->cleanUpTempFile($mediaFile);

        static::assertSame($blob, $this->fileLoader->loadMediaFile($mediaId, $context));
        static::assertFileDoesNotExist($mediaFile->getFileName());

        $this->mediaRepository->delete([['id' => $mediaId]], $context);
    }

    public function testLoadMediaFileStream(): void
    {
        $context = Context::createDefaultContext();
        $blob = \file_get_contents(self::TEST_IMAGE);
        static::assertIsString($blob);
        $mediaFile = $this->fileFetcher->fetchBlob($blob, 'png', 'image/png');
        $mediaId = Uuid::randomHex();
        $this->mediaRepository->create([['id' => $mediaId]], $context);
        $this->fileSaver->persistFileToMedia($mediaFile, $mediaId . '.png', $mediaId, $context);
        $this->fileFetcher->cleanUpTempFile($mediaFile);

        static::assertSame($blob, (string) $this->fileLoader->loadMediaFileStream($mediaId, $context));
        static::assertFileDoesNotExist($mediaFile->getFileName());

        $this->mediaRepository->delete([['id' => $mediaId]], $context);
    }

    public function testLoadMediaEntityFile(): void
    {
        $context = Context::createDefaultContext();
        $blob = \file_get_contents(self::TEST_IMAGE);
        static::assertIsString($blob);
        $mediaFile = $this->fileFetcher->fetchBlob($blob, 'png', 'image/png');
        $mediaId = Uuid::randomHex();
        $this->mediaRepository->create([['id' => $mediaId]], $context);
        $this->fileSaver->persistFileToMedia($mediaFile, $mediaId . '.png', $mediaId, $context);
        $this->fileFetcher->cleanUpTempFile($mediaFile);

        $media = $this->mediaRepository->search(
            new Criteria([$mediaId]),
            $context
        )->getEntities()->first();
        static::assertInstanceOf(MediaEntity::class, $media);

        static::assertSame($blob, $this->fileLoader->loadMediaEntityFile($media));
        static::assertFileDoesNotExist($mediaFile->getFileName());

        $this->mediaRepository->delete([['id' => $mediaId]], $context);
    }

    public function testLoadMediaEntityFileStream(): void
    {
        $context = Context::createDefaultContext();
        $blob = \file_get_contents(self::TEST_IMAGE);
        static::assertIsString($blob);
        $mediaFile = $this->fileFetcher->fetchBlob($blob, 'png', 'image/png');
        $mediaId = Uuid::randomHex();
        $this->mediaRepository->create([['id' => $mediaId]], $context);
        $this->fileSaver->persistFileToMedia($mediaFile, $mediaId . '.png', $mediaId, $context);
        $this->fileFetcher->cleanUpTempFile($mediaFile);

        $media = $this->mediaRepository->search(
            new Criteria([$mediaId]),
            $context
        )->getEntities()->first();
        static::assertInstanceOf(MediaEntity::class, $media);

        static::assertSame($blob, (string) $this->fileLoader->loadMediaEntityFileStream($media));
        static::assertFileDoesNotExist($mediaFile->getFileName());

        $this->mediaRepository->delete([['id' => $mediaId]], $context);
    }
}
