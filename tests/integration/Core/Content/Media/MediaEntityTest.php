<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Media;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Test\Media\MediaFixtures;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class MediaEntityTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $repository;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('media.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testWriteReadMinimalFields(): void
    {
        $media = $this->getEmptyMedia();

        $criteria = $this->getIdCriteria($media->getId());
        $result = $this->repository->search($criteria, $this->context);
        $media = $result->getEntities()->first();

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame($media->getId(), $media->getId());
    }

    public function testThumbnailsAreConvertedToStructWhenFetchedFromDb(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getMediaWithThumbnail();

        $criteria = $this->getIdCriteria($media->getId());
        $searchResult = $this->repository->search($criteria, $this->context);
        $fetchedMedia = $searchResult->getEntities()->get($media->getId());

        static::assertInstanceOf(MediaEntity::class, $fetchedMedia);
        static::assertInstanceOf(MediaThumbnailCollection::class, $fetchedMedia->getThumbnails());

        $persistedThumbnail = $fetchedMedia->getThumbnails()->first();
        static::assertInstanceOf(MediaThumbnailEntity::class, $persistedThumbnail);
        static::assertSame(200, $persistedThumbnail->getWidth());
        static::assertSame(200, $persistedThumbnail->getHeight());
    }

    public function testDeleteMediaWithTags(): void
    {
        $media = $this->getEmptyMedia();

        $this->repository->update([
            [
                'id' => $media->getId(),
                'tags' => [['name' => 'test tag']],
            ],
        ], $this->context);

        $this->repository->delete([['id' => $media->getId()]], $this->context);
    }

    private function getIdCriteria(string $mediaId): Criteria
    {
        $criteria = new Criteria();
        $criteria->setOffset(0);
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('media.id', $mediaId));

        return $criteria;
    }
}
