<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Extension\MediaRouteExtension;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Media\MediaException;
use Contena\Core\Content\Media\Channel\MediaRoute;
use Contena\Core\Content\Media\Channel\MediaRouteResponse;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
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
            new ExtensionDispatcher(new EventDispatcher()),
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

        $channelContext = $this->createMock(ChannelContext::class);
        $channelContext
            ->expects($this->once())
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $request = new Request([], ['ids' => $ids]);

        $mediaEntitySearchResult = new EntitySearchResult(
            'media',
            2,
            new MediaCollection([$mediaEntity1, $mediaEntity2]),
            null,
            new Criteria(),
            Context::createDefaultContext()
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

        $mediaRoute = new MediaRoute($mediaRepository, $cacheTagCollector, new ExtensionDispatcher(new EventDispatcher()));

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

        $channelContext = $this->createMock(ChannelContext::class);
        $channelContext
            ->expects($this->never())
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $request = new Request([], ['ids' => '']);

        $this->mediaRoute->load($request, $channelContext);
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();
        $response = static::createStub(MediaRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('media-route.load.pre', static function (MediaRouteExtension $extension) use ($request, $context, $response): void {
            static::assertSame(['request' => $request, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new MediaRoute(
            static::createStub(EntityRepository::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context));
    }
}
