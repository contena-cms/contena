<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Channel\MediaRoute;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Media\MediaException;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(MediaRoute::class)]
class MediaRouteTest extends TestCase
{
    /**
     * @var EntityRepository<MediaCollection>&Stub
     */
    private EntityRepository&Stub $mediaRepository;

    private CacheTagCollector&Stub $cacheTagCollector;

    private MediaRoute $mediaRoute;

    protected function setUp(): void
    {
        $this->mediaRepository = static::createStub(EntityRepository::class);
        $this->cacheTagCollector = static::createStub(CacheTagCollector::class);
        $this->mediaRoute = new MediaRoute(
            $this->mediaRepository,
            $this->cacheTagCollector,
        );
    }

    public function testLoadReturnsMediaRouteResponse(): void
    {
        $ids = ['testMediaId1', 'testMediaId2'];

        $mediaEntity1 = new MediaEntity();
        $mediaEntity1->setId('testMediaId1');
        $mediaEntity1->setPath('testPath1');

        $mediaEntity2 = new MediaEntity();
        $mediaEntity2->setId('testMediaId2');
        $mediaEntity2->setPath('testPath2');

        $channelContext = Generator::generateChannelContext();

        $request = new Request([], ['ids' => $ids]);

        $mediaEntitySearchResult = new EntitySearchResult(
            2,
            new MediaCollection([$mediaEntity1, $mediaEntity2]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $mediaRepository = $this->createMock(EntityRepository::class);
        $mediaRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($mediaEntitySearchResult);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with('media-testMediaId1', 'media-testMediaId2');

        $mediaRoute = new MediaRoute($mediaRepository, $cacheTagCollector);

        $response = $mediaRoute->load($request, $channelContext);
        $mediaCollection = $response->getMediaCollection();
        $firstMediaEntity = $mediaCollection->first();

        static::assertCount(2, $mediaCollection);
        static::assertInstanceOf(MediaEntity::class, $firstMediaEntity);
        static::assertSame('testMediaId1', $firstMediaEntity->getId());
        static::assertSame('testPath1', $firstMediaEntity->getPath());
    }

    public function testLoadThrowsMediaExceptionWhenMediaNotFound(): void
    {
        $this->expectExceptionObject(MediaException::emptyMediaId());

        $request = new Request([], ['ids' => '']);

        $this->mediaRoute->load($request, Generator::generateChannelContext());
    }
}
