<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\Exception\CategoryNotFoundException;
use Contena\Core\Content\Category\Extension\NavigationRouteExtension;
use Contena\Core\Content\Category\Channel\NavigationRoute;
use Contena\Core\Content\Category\Channel\NavigationRouteResponse;
use Contena\Core\Content\Category\Service\DefaultCategoryLevelLoader;
use Contena\Core\Content\Category\Tree\CategoryTreePathResolver;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(NavigationRoute::class)]
class NavigationRouteTest extends TestCase
{
    private NavigationRoute $navigationRoute;

    private Connection&MockObject $connection;

    /**
     * @var ChannelRepository<CategoryCollection>&Stub
     */
    private ChannelRepository&Stub $categoryRepository;

    private CacheTagCollector&Stub $cacheTagCollector;

    private CategoryTreePathResolver&Stub $categoryTreePathResolver;

    private DefaultCategoryLevelLoader&Stub $defaultCategoryLevelLoader;

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->categoryRepository = static::createStub(ChannelRepository::class);
        $this->cacheTagCollector = static::createStub(CacheTagCollector::class);
        $this->categoryTreePathResolver = static::createStub(CategoryTreePathResolver::class);
        $this->defaultCategoryLevelLoader = static::createStub(DefaultCategoryLevelLoader::class);

        $this->navigationRoute = $this->createRoute();

        $this->channelContext = Generator::generateChannelContext();
    }

    public function testLoadAddsCacheTagsCorrectly(): void
    {
        $activeId = Uuid::randomHex();
        $rootId = Generator::NAVIGATION_CATEGORY;
        $request = new Request();
        $criteria = new Criteria();

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'LOWER(HEX(`id`))' => $activeId,
                    'path' => '|' . $rootId . '|' . $activeId . '|',
                    'level' => 2,
                ],
                [
                    'LOWER(HEX(`id`))' => $rootId,
                    'path' => '|' . $rootId . '|',
                    'level' => 1,
                ],
            ]);

        $categoryRepository = $this->createMock(ChannelRepository::class);
        $categoryRepository
            ->expects($this->never())
            ->method('search');

        $categoryTreePathResolver = $this->createMock(CategoryTreePathResolver::class);
        $categoryTreePathResolver
            ->expects($this->once())
            ->method('getAdditionalPathsToLoad')
            ->willReturn([]);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with(NavigationRoute::ALL_TAG);

        $this->createRoute($categoryRepository, $cacheTagCollector, $categoryTreePathResolver)->load(
            $activeId,
            $rootId,
            $request,
            $this->channelContext,
            $criteria
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLoadAddsDeprecatedCacheTagsCorrectly(): void
    {
        $activeId = Uuid::randomHex();
        $rootId = Generator::NAVIGATION_CATEGORY;
        $request = new Request();
        $criteria = new Criteria();

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'LOWER(HEX(`id`))' => $activeId,
                    'path' => '|' . $rootId . '|' . $activeId . '|',
                    'level' => 2,
                ],
                [
                    'LOWER(HEX(`id`))' => $rootId,
                    'path' => '|' . $rootId . '|',
                    'level' => 1,
                ],
            ]);

        $categoryRepository = $this->createMock(ChannelRepository::class);
        $categoryRepository
            ->expects($this->never())
            ->method('search');

        $categoryTreePathResolver = $this->createMock(CategoryTreePathResolver::class);
        $categoryTreePathResolver
            ->expects($this->once())
            ->method('getAdditionalPathsToLoad')
            ->willReturn([]);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with(
                NavigationRoute::ALL_TAG,
                NavigationRoute::buildName($this->channelContext->getChannelId()),
                NavigationRoute::buildName($activeId)
            );

        $this->createRoute($categoryRepository, $cacheTagCollector, $categoryTreePathResolver)->load(
            $activeId,
            $rootId,
            $request,
            $this->channelContext,
            $criteria
        );
    }

    public function testLoadWithInvalidCategoryThrowsException(): void
    {
        $activeId = Uuid::randomHex();
        $rootId = Uuid::randomHex();
        $request = new Request();
        $criteria = new Criteria();

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->expectExceptionObject(new CategoryNotFoundException($activeId));

        $this->navigationRoute->load(
            $activeId,
            $rootId,
            $request,
            $this->channelContext,
            $criteria
        );
    }

    public function testPublishesExtension(): void
    {
        $activeId = Uuid::randomHex();
        $rootId = Uuid::randomHex();
        $request = new Request();
        $criteria = new Criteria();
        $response = static::createStub(NavigationRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('navigation-route.load.pre', function (NavigationRouteExtension $extension) use ($activeId, $rootId, $request, $criteria, $response): void {
            static::assertSame([
                'activeId' => $activeId,
                'rootId' => $rootId,
                'request' => $request,
                'context' => $this->channelContext,
                'criteria' => $criteria,
            ], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $this->connection->expects($this->never())->method('fetchAllAssociative');

        $route = $this->createRoute(extensions: new ExtensionDispatcher($dispatcher));

        static::assertSame($response, $route->load($activeId, $rootId, $request, $this->channelContext, $criteria));
    }

    /**
     * @param (ChannelRepository<CategoryCollection>&MockObject)|null $categoryRepository
     */
    private function createRoute(
        ?ChannelRepository $categoryRepository = null,
        ?CacheTagCollector $cacheTagCollector = null,
        ?CategoryTreePathResolver $categoryTreePathResolver = null,
        ?ExtensionDispatcher $extensions = null,
    ): NavigationRoute {
        return new NavigationRoute(
            $this->connection,
            $categoryRepository ?? $this->categoryRepository,
            $cacheTagCollector ?? $this->cacheTagCollector,
            $categoryTreePathResolver ?? $this->categoryTreePathResolver,
            $this->defaultCategoryLevelLoader,
            $extensions ?? new ExtensionDispatcher(new EventDispatcher()),
        );
    }
}
