<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Group('slow')]
class MediaFolderRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    private const string FIXTURE_FILE = __DIR__ . '/../fixtures/contena-logo.png';

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    private Context $context;

    /**
     * @var EntityRepository<MediaFolderCollection>
     */
    private EntityRepository $folderRepository;

    protected function setUp(): void
    {
        $this->folderRepository = static::getContainer()->get('media_folder.repository');
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testPrivateFolderNotReadable(): void
    {
        $folderId = Uuid::randomHex();
        $configId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $folderId,
                'name' => 'testFolder',
                'configuration' => [
                    'id' => $configId,
                    'private' => true,
                ],
            ],
        ], $this->context);

        $folderRepository = $this->folderRepository;
        $media = null;
        $this->context->scope(Context::USER_SCOPE, static function (Context $context) use (&$media, $folderId, $folderRepository): void {
            $media = $folderRepository->search(new Criteria([$folderId]), $context);
        });

        static::assertNotNull($media);
        static::assertCount(0, $media->getEntities());
    }

    public function testFolderWithoutConfigIsReadable(): void
    {
        $folderId = Uuid::randomHex();
        $configId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $folderId,
                'name' => 'testFolder',
                'configurationId' => $configId,
            ],
        ], $this->context);

        $media = $this->folderRepository->search(new Criteria([$folderId]), $this->context);

        static::assertCount(1, $media->getEntities());
    }

    public function testDeleteFolderAlsoDeletesMedia(): void
    {
        $folderId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $folderId,
                'name' => 'testFolder',
                'configuration' => [],
            ],
        ], $this->context);

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $mediaId . '-' . new \DateTime()->getTimestamp(),
                    'mediaFolderId' => $folderId,
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->getEntities()->get($mediaId);
        static::assertInstanceOf(MediaEntity::class, $media);

        $mediaPath = $media->getPath();

        $file = fopen(self::FIXTURE_FILE, 'r');
        static::assertIsResource($file);
        $this->getPublicFilesystem()->writeStream($mediaPath, $file);

        $this->folderRepository->delete([['id' => $folderId]], $this->context);

        static::assertSame(0, $this->folderRepository->search(new Criteria([$folderId]), $this->context)->getTotal());
        static::assertSame(0, $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->getTotal());

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($mediaPath));
    }

    public function testDeleteFolderAlsoDeletesSubFoldersWithMedia(): void
    {
        $childFolderId = Uuid::randomHex();
        $parentFolderId = Uuid::randomHex();
        $childMediaId = Uuid::randomHex();
        $parentMediaId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $parentFolderId,
                'name' => 'parent',
                'configuration' => [],
            ],
            [
                'id' => $childFolderId,
                'name' => 'testFolder',
                'configuration' => [],
                'parentId' => $parentFolderId,
            ],
        ], $this->context);

        $this->mediaRepository->create(
            [
                [
                    'id' => $childMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $childMediaId . '-' . new \DateTime()->getTimestamp(),
                    'mediaFolderId' => $childFolderId,
                ],
                [
                    'id' => $parentMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $parentMediaId . '-' . new \DateTime()->getTimestamp(),
                    'mediaFolderId' => $parentFolderId,
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context);

        $childMedia = $media->getEntities()->get($childMediaId);
        static::assertInstanceOf(MediaEntity::class, $childMedia);

        $parentMedia = $media->getEntities()->get($parentMediaId);
        static::assertInstanceOf(MediaEntity::class, $parentMedia);

        $childMediaPath = $childMedia->getPath();
        $parentMediaPath = $parentMedia->getPath();

        $file = fopen(self::FIXTURE_FILE, 'r');
        static::assertIsResource($file);
        $this->getPublicFilesystem()->writeStream($childMediaPath, $file);
        $this->getPublicFilesystem()->writeStream($parentMediaPath, $file);

        $this->folderRepository->delete([['id' => $parentFolderId]], $this->context);

        static::assertSame(0, $this->folderRepository->search(new Criteria([$parentFolderId, $childFolderId]), $this->context)->getTotal());
        static::assertSame(0, $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context)->getTotal());

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($childMediaPath));
        static::assertFalse($this->getPublicFilesystem()->has($parentMediaPath));
    }

    public function testDeleteFolderDoesNotTouchParent(): void
    {
        $childFolderId = Uuid::randomHex();
        $parentFolderId = Uuid::randomHex();
        $childMediaId = Uuid::randomHex();
        $parentMediaId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $parentFolderId,
                'name' => 'parent',
                'configuration' => [],
            ],
            [
                'id' => $childFolderId,
                'name' => 'testFolder',
                'configuration' => [],
                'parentId' => $parentFolderId,
            ],
        ], $this->context);

        $this->mediaRepository->create(
            [
                [
                    'id' => $childMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'path' => 'media/test_media_1.png',
                    'fileName' => $childMediaId . '-' . new \DateTime()->getTimestamp(),
                    'mediaFolderId' => $childFolderId,
                ],
                [
                    'id' => $parentMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'path' => 'media/test_media_2.png',
                    'fileName' => $parentMediaId . '-' . new \DateTime()->getTimestamp(),
                    'mediaFolderId' => $parentFolderId,
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context);

        $childMedia = $media->getEntities()->get($childMediaId);
        static::assertInstanceOf(MediaEntity::class, $childMedia);

        $parentMedia = $media->getEntities()->get($parentMediaId);
        static::assertInstanceOf(MediaEntity::class, $parentMedia);

        $childMediaPath = $childMedia->getPath();
        $parentMediaPath = $parentMedia->getPath();

        $file = fopen(self::FIXTURE_FILE, 'r');
        static::assertIsResource($file);
        $this->getPublicFilesystem()->writeStream($childMediaPath, $file);
        $this->getPublicFilesystem()->writeStream($parentMediaPath, $file);

        $this->folderRepository->delete([['id' => $childFolderId]], $this->context);

        static::assertArrayHasKey($parentFolderId, $this->folderRepository->search(new Criteria([$parentFolderId, $childFolderId]), $this->context)->getEntities()->getIds());
        static::assertArrayHasKey($parentMediaId, $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context)->getEntities()->getIds());

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($childMediaPath));
        static::assertTrue($this->getPublicFilesystem()->has($parentMediaPath));
    }

    public function testDeleteFolderParentAndChild(): void
    {
        $childFolderId = Uuid::randomHex();
        $parentFolderId = Uuid::randomHex();
        $childMediaId = Uuid::randomHex();
        $parentMediaId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $parentFolderId,
                'name' => 'parent',
                'configuration' => [],
            ],
            [
                'id' => $childFolderId,
                'name' => 'testFolder',
                'configuration' => [],
                'parentId' => $parentFolderId,
            ],
        ], $this->context);

        $this->mediaRepository->create(
            [
                [
                    'id' => $childMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $childMediaId . '-' . new \DateTime()->getTimestamp(),
                    'mediaFolderId' => $childFolderId,
                ],
                [
                    'id' => $parentMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $parentMediaId . '-' . new \DateTime()->getTimestamp(),
                    'mediaFolderId' => $parentFolderId,
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context);

        $childMedia = $media->getEntities()->get($childMediaId);
        static::assertInstanceOf(MediaEntity::class, $childMedia);

        $parentMedia = $media->getEntities()->get($parentMediaId);
        static::assertInstanceOf(MediaEntity::class, $parentMedia);

        $childMediaPath = $childMedia->getPath();
        $parentMediaPath = $parentMedia->getPath();

        $file = fopen(self::FIXTURE_FILE, 'r');
        static::assertIsResource($file);
        $this->getPublicFilesystem()->writeStream($childMediaPath, $file);
        $this->getPublicFilesystem()->writeStream($parentMediaPath, $file);

        $this->folderRepository->delete([['id' => $parentFolderId], ['id' => $childFolderId]], $this->context);

        static::assertSame(0, $this->folderRepository->search(new Criteria([$parentFolderId, $childFolderId]), $this->context)->getTotal());
        static::assertSame(0, $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context)->getTotal());

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($childMediaPath));
        static::assertFalse($this->getPublicFilesystem()->has($parentMediaPath));
    }

    public function testDeleteFolderChildAndParent(): void
    {
        $childFolderId = Uuid::randomHex();
        $parentFolderId = Uuid::randomHex();
        $childMediaId = Uuid::randomHex();
        $parentMediaId = Uuid::randomHex();

        $this->folderRepository->create([
            [
                'id' => $parentFolderId,
                'name' => 'parent',
                'configuration' => [],
            ],
            [
                'id' => $childFolderId,
                'name' => 'testFolder',
                'configuration' => [],
                'parentId' => $parentFolderId,
            ],
        ], $this->context);

        $this->mediaRepository->create(
            [
                [
                    'id' => $childMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $childMediaId . '-' . new \DateTime()->getTimestamp(),
                    'mediaFolderId' => $childFolderId,
                ],
                [
                    'id' => $parentMediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $parentMediaId . '-' . new \DateTime()->getTimestamp(),
                    'mediaFolderId' => $parentFolderId,
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context);

        $childMedia = $media->getEntities()->get($childMediaId);
        static::assertInstanceOf(MediaEntity::class, $childMedia);

        $parentMedia = $media->getEntities()->get($parentMediaId);
        static::assertInstanceOf(MediaEntity::class, $parentMedia);

        $childMediaPath = $childMedia->getPath();
        $parentMediaPath = $parentMedia->getPath();

        $file = fopen(self::FIXTURE_FILE, 'r');
        static::assertIsResource($file);
        $this->getPublicFilesystem()->writeStream($childMediaPath, $file);
        $this->getPublicFilesystem()->writeStream($parentMediaPath, $file);

        $this->folderRepository->delete([['id' => $childFolderId], ['id' => $parentFolderId]], $this->context);

        static::assertSame(0, $this->folderRepository->search(new Criteria([$parentFolderId, $childFolderId]), $this->context)->getTotal());
        static::assertSame(0, $this->mediaRepository->search(new Criteria([$childMediaId, $parentMediaId]), $this->context)->getTotal());

        $this->runWorker();

        static::assertFalse($this->getPublicFilesystem()->has($childMediaPath));
        static::assertFalse($this->getPublicFilesystem()->has($parentMediaPath));
    }
}
