<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class MediaVisibilityRestrictionSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    /**
     * @var EntityRepository<MediaFolderCollection>
     */
    private EntityRepository $mediaFolderRepository;

    private Connection $connection;

    private Context $channelContext;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->mediaFolderRepository = static::getContainer()->get('media_folder.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();

        $this->channelContext = Context::createDefaultContext(new ChannelApiSource($this->ids->get('sales-channel')));
    }

    public function testSearchPublicMediaIsFound(): void
    {
        $privateFolderId = $this->createMediaFolder('private-media-folder', 'private-folder', true);
        $publicFolderId = $this->createMediaFolder('public-media-folder', 'public-folder', false);

        $privateMediaId = $this->createMedia('private-media', 'private-file', $privateFolderId, true);
        $publicMediaId = $this->createMedia('public-media.1', 'public-file-1', $publicFolderId, false);
        $publicMediaInPrivateFolder = $this->createMedia('public-media.2', 'public-file-2', $privateFolderId, false);

        $criteria = new Criteria([
            $privateMediaId,
            $publicMediaId,
            $publicMediaInPrivateFolder,
        ]);
        $result = $this->mediaRepository->search($criteria, $this->channelContext);
        $mediaIds = array_map(static fn ($media) => $media->getId(), $result->getEntities()->getElements());
        static::assertNotContains($privateMediaId, $mediaIds, 'Private media should not be found');
        static::assertContains($publicMediaInPrivateFolder, $mediaIds, 'Public media in private folder should be found');
        static::assertContains($publicMediaId, $mediaIds, 'Public media should be found');
    }

    public function testSearchPrivateMediaIsRestricted(): void
    {
        $privateFolderId = $this->createMediaFolder('private-media-folder', 'private-folder', true);
        $publicFolderId = $this->createMediaFolder('public-media-folder', 'public-folder', false);

        $privateMediaId = $this->createMedia('private-media', 'private-file', $privateFolderId, true);
        $publicMediaId = $this->createMedia('public-media.1', 'public-file-1', $publicFolderId, false);
        $publicMediaInPrivateFolder = $this->createMedia('public-media.2', 'public-file-2', $privateFolderId, false);

        $criteria = new Criteria([
            $privateMediaId,
            $publicMediaId,
            $publicMediaInPrivateFolder,
        ]);
        $criteria->addFilter(new EqualsFilter('private', true));
        $result = $this->mediaRepository->search($criteria, $this->channelContext);
        $mediaIds = array_map(static fn ($media) => $media->getId(), $result->getEntities()->getElements());
        static::assertNotContains($privateMediaId, $mediaIds, 'Private media should not be found');
        static::assertNotContains($publicMediaId, $mediaIds, 'Public media should not be found when filtering for private');
        static::assertCount(0, $mediaIds, 'No media should be returned when searching for private media');
    }

    public function testSearchPrivateBlogDownloadMediaIsAllowed(): void
    {
        $privateFolderId = $this->createMediaFolder('private-media-folder', 'private-folder', true);
        $blogDownloadFolderId = $this->getBlogDownloadFolderId();

        $privateMediaId = $this->createMedia('private-media', 'private-file', $privateFolderId, true);
        $privateBlogDownloadMediaId = $this->createMedia(
            'private-blog-download-media',
            'private-blog-download-file',
            $blogDownloadFolderId,
            true
        );

        $criteria = new Criteria([
            $privateMediaId,
            $privateBlogDownloadMediaId,
        ]);
        $result = $this->mediaRepository->search($criteria, $this->channelContext);
        $mediaIds = array_map(static fn ($media) => $media->getId(), $result->getEntities()->getElements());

        static::assertNotContains($privateMediaId, $mediaIds, 'Private media outside the blog download folder should not be found');
        static::assertContains($privateBlogDownloadMediaId, $mediaIds, 'Private blog download media should be found');
    }

    public function testSearchPublicMediaFolderIsFound(): void
    {
        $privateFolderId = $this->createMediaFolder('private-folder', 'private-folder', true);
        $publicFolderId = $this->createMediaFolder('public-folder', 'public-folder', false);

        $criteria = new Criteria([
            $privateFolderId,
            $publicFolderId,
        ]);
        $result = $this->mediaFolderRepository->search($criteria, $this->channelContext);
        $folderIds = array_map(static fn ($folder) => $folder->getId(), $result->getEntities()->getElements());
        static::assertNotContains($privateFolderId, $folderIds, 'Private folder should not be found');
        static::assertContains($publicFolderId, $folderIds, 'Public folder should be found');
    }

    public function testSearchPrivateMediaFolderIsRestricted(): void
    {
        $privateFolderId = $this->createMediaFolder('private-folder', 'private-folder', true);
        $publicFolderId = $this->createMediaFolder('public-folder', 'public-folder', false);

        $criteria = new Criteria([
            $privateFolderId,
            $publicFolderId,
        ]);
        $criteria->addFilter(new EqualsFilter('configuration.private', true));
        $result = $this->mediaFolderRepository->search($criteria, $this->channelContext);
        $folderIds = array_map(static fn ($folder) => $folder->getId(), $result->getEntities()->getElements());
        static::assertNotContains($privateFolderId, $folderIds, 'Private folder should not be found');
        static::assertNotContains($publicFolderId, $folderIds, 'Public folder should not be found when filtering for private');
        static::assertCount(0, $folderIds, 'No folder should be returned when searching for private folders');
    }

    public function testFilterAggregationForPublicMediaIsExecuted(): void
    {
        $privateFolderId = $this->createMediaFolder('private-media-folder', 'private-folder', true);
        $publicFolderId = $this->createMediaFolder('public-media-folder', 'public-folder', false);

        $privateMediaId = $this->createMedia('private-media', 'private-file', $privateFolderId, true);
        $publicMediaId = $this->createMedia('public-media.1', 'public-file-1', $publicFolderId, false);
        $publicMediaInPrivateFolder = $this->createMedia('public-media.2', 'public-file-2', $privateFolderId, false);

        $criteria = new Criteria([
            $privateMediaId,
            $publicMediaId,
            $publicMediaInPrivateFolder,
        ]);
        $criteria->addAggregation(
            new FilterAggregation(
                'public-media',
                new CountAggregation('public-media-count', 'id'),
                [new EqualsFilter('private', false)]
            )
        );
        $result = $this->mediaRepository->search($criteria, $this->channelContext);
        static::assertCount(2, $result->getEntities());

        static::assertTrue($result->getEntities()->has($this->ids->get('public-media.1')));
        static::assertTrue($result->getEntities()->has($this->ids->get('public-media.2')));

        $countResult = $result->getAggregations()->get('public-media-count');
        static::assertInstanceOf(CountResult::class, $countResult);
        static::assertSame(2, $countResult->getCount(), 'Public media should be counted in aggregation');
    }

    public function testFilterAggregationForPrivateMediaIsRestricted(): void
    {
        $privateFolderId = $this->createMediaFolder('private-media-folder', 'private-folder', true);
        $publicFolderId = $this->createMediaFolder('public-media-folder', 'public-folder', false);

        $privateMediaId = $this->createMedia('private-media', 'private-file', $privateFolderId, true);
        $publicMediaId = $this->createMedia('public-media.1', 'public-file-1', $publicFolderId, false);
        $publicMediaInPrivateFolder = $this->createMedia('public-media.2', 'public-file-2', $privateFolderId, false);

        $criteria = new Criteria([
            $privateMediaId,
            $publicMediaId,
            $publicMediaInPrivateFolder,
        ]);
        $criteria->addAggregation(
            new FilterAggregation(
                'private-media',
                new CountAggregation('private-media-count', 'id'),
                [new EqualsFilter('private', true)]
            )
        );
        $result = $this->mediaRepository->search($criteria, $this->channelContext);
        $countResult = $result->getAggregations()->get('private-media-count');
        static::assertInstanceOf(CountResult::class, $countResult);
        static::assertSame(0, $countResult->getCount(), 'No private media should be counted in aggregation');
    }

    public function testOtherAggregationForPrivateMediaIsRestricted(): void
    {
        $privateFolderId = $this->createMediaFolder('private-media-folder', 'private-folder', true);
        $publicFolderId = $this->createMediaFolder('public-media-folder', 'public-folder', false);

        $privateMediaId = $this->createMedia('private-media', 'private-file', $privateFolderId, true);
        $publicMediaId = $this->createMedia('public-media.1', 'public-file-1', $publicFolderId, false);
        $publicMediaInPrivateFolder = $this->createMedia('public-media.2', 'public-file-2', $privateFolderId, false);

        $criteria = new Criteria([
            $privateMediaId,
            $publicMediaId,
            $publicMediaInPrivateFolder,
        ]);
        $criteria->addAggregation(
            new TermsAggregation(
                'private-media-terms',
                'private'
            )
        );
        $result = $this->mediaRepository->search($criteria, $this->channelContext);
        $termsResult = $result->getAggregations()->get('private-media-terms');
        static::assertInstanceOf(TermsResult::class, $termsResult);
        $buckets = $termsResult->getBuckets();
        $bucketValues = array_map(static fn (Bucket $b) => $b->getKey(), $buckets);
        static::assertNotContains('1', $bucketValues, 'There should be no bucket for private media');
        static::assertContains('0', $bucketValues, 'There should be a bucket for public media');

        $publicBucket = array_filter($buckets, static fn (Bucket $b) => $b->getKey() === '0');
        static::assertCount(1, $publicBucket, 'There should be exactly one public media bucket');
        $publicCount = $publicBucket[0];
        static::assertInstanceOf(Bucket::class, $publicCount);
        static::assertSame(2, $publicCount->getCount(), 'Public media bucket should have count 2');
    }

    private function createMediaFolder(string $key, string $name, bool $isPrivate): string
    {
        $id = $this->ids->get($key);
        $this->mediaFolderRepository->create([
            [
                'id' => $this->ids->get($key),
                'name' => $name,
                'useParentConfiguration' => false,
                'configuration' => [
                    'private' => $isPrivate,
                ],
            ],
        ], $this->channelContext);

        return $id;
    }

    private function createMedia(string $key, string $fileName, string $folderId, bool $isPrivate): string
    {
        $id = $this->ids->get($key);
        $this->mediaRepository->create([
            [
                'id' => $id,
                'mediaFolderId' => $folderId,
                'private' => $isPrivate,
                'fileName' => $fileName,
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
            ],
        ], $this->channelContext);

        return $id;
    }

    private function getBlogDownloadFolderId(): string
    {
        $folderId = $this->connection->fetchOne(
            <<<'SQL'
                SELECT LOWER(HEX(`media_folder`.`id`))
                FROM `media_folder`
                INNER JOIN `media_default_folder`
                    ON `media_default_folder`.`id` = `media_folder`.`default_folder_id`
                WHERE `media_default_folder`.`entity` = :entity
            SQL,
            ['entity' => 'blog_download']
        );

        static::assertIsString($folderId);

        return $folderId;
    }
}
