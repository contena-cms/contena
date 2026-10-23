<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewCollection;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewDefinition;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewRoute;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewRouteResponse;
use Contena\Core\Content\Blog\Extension\BlogReviewRouteExtension;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator;
use Contena\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\CriteriaArrayConverter;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Util\Base64;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriterGateway;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(BlogReviewRoute::class)]
class BlogReviewRouteTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<BlogReviewCollection>
     */
    private Stub&EntityRepository $repository;

    private StaticSystemConfigService $config;

    private CacheTagCollector&Stub $cacheTagCollector;

    private BlogReviewRoute $route;

    protected function setUp(): void
    {
        $this->repository = static::createStub(EntityRepository::class);
        $this->config = new StaticSystemConfigService([
            'test' => [
                'core.listing.showReview' => true,
                'core.listing.reviewsPerPage' => 10,
                'core.basicInformation.email' => 'noreply@example.com',
            ],
            'testReviewPerPageAboveMaxLimit' => [
                'core.listing.showReview' => true,
                'core.listing.reviewsPerPage' => 150,
                'core.basicInformation.email' => 'noreply@example.com',
            ],
            'testReviewNotActive' => [
                'core.listing.showReview' => false,
                'core.basicInformation.email' => 'noreply@example.com',
            ],
        ]);

        $this->cacheTagCollector = static::createStub(CacheTagCollector::class);

        $this->route = $this->createRoute();
    }

    public function testLoad(): void
    {
        $blogId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $channelContext = $this->createMock(ChannelContext::class);
        $channelContext->expects($this->once())->method('getUser')->willReturn($user);
        $channelContext->expects($this->exactly(2))->method('getChannelId')->willReturn('test');
        $channelContext->expects($this->exactly(1))->method('getContext')->willReturn($context);

        $expectedCriteria = new Criteria();
        $expectedCriteria->setTitle('blog-review-route');
        $expectedCriteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('status', true),
                    new EqualsFilter('userId', $user->getId()),
                ]),
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('blog.id', $blogId),
                    new EqualsFilter('blog.parentId', $blogId),
                ]),
            ])
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')
            ->with($expectedCriteria, $context);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with($this->route::buildName($blogId));

        $this->createRoute($repository, $cacheTagCollector)->load(
            $blogId,
            new Request(),
            $channelContext,
            new Criteria(),
        );
    }

    public function testLoadAppliesConfiguredReviewsPerPageWhenRequestHasNoLimit(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn('test');
        $channelContext->method('getContext')->willReturn(Context::createDefaultContext());

        // Simulates the criteria the RequestCriteriaBuilder produces without an explicit limit.
        $criteria = new Criteria();
        $criteria->setLimit(100);
        $criteria->addState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST);

        $searchedCriteria = null;
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use (&$searchedCriteria): EntitySearchResult {
                $searchedCriteria = $criteria;

                return new EntitySearchResult(
                    'blog_review',
                    0,
                    new BlogReviewCollection(),
                    null,
                    $criteria,
                    Context::createDefaultContext()
                );
            });

        $this->createRoute($repository)->load(Uuid::randomHex(), new Request(), $channelContext, $criteria);

        static::assertInstanceOf(Criteria::class, $searchedCriteria);
        static::assertSame(10, $searchedCriteria->getLimit());
        static::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $searchedCriteria->getTotalCountMode());
        static::assertFalse($searchedCriteria->hasState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST));
    }

    public function testLoadRecomputesOffsetForConfiguredReviewsPerPage(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn('test');
        $channelContext->method('getContext')->willReturn(Context::createDefaultContext());

        // Page 2 without an explicit limit: offset was derived from the max limit (100).
        $criteria = new Criteria();
        $criteria->setLimit(100);
        $criteria->setOffset(100);
        $criteria->addState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST);

        $searchedCriteria = null;
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use (&$searchedCriteria): EntitySearchResult {
                $searchedCriteria = $criteria;

                return new EntitySearchResult(
                    'blog_review',
                    0,
                    new BlogReviewCollection(),
                    null,
                    $criteria,
                    Context::createDefaultContext()
                );
            });

        $this->createRoute($repository)->load(Uuid::randomHex(), new Request(), $channelContext, $criteria);

        static::assertInstanceOf(Criteria::class, $searchedCriteria);
        static::assertSame(10, $searchedCriteria->getLimit());
        static::assertSame(10, $searchedCriteria->getOffset());
    }

    #[DataProvider('requestedCountModeProvider')]
    public function testLoadPreservesRequestedCountMode(string $requestType, int|string|null $countMode, int $expectedMode): void
    {
        $context = Generator::generateChannelContext();
        $this->config->set('core.listing.showReview', true, $context->getChannelId());
        $this->config->set('core.listing.reviewsPerPage', 10, $context->getChannelId());

        $definition = new BlogReviewDefinition();
        $registry = new StaticDefinitionInstanceRegistry([$definition], Validation::createValidator(), new StaticEntityWriterGateway());
        $parser = new AggregationParser();
        $builder = new RequestCriteriaBuilder(
            $parser,
            new ApiCriteriaValidator($registry),
            new CriteriaArrayConverter($parser),
            new CompressedCriteriaDecoder(),
            100,
        );

        $payload = ['page' => 3];
        if ($countMode !== null) {
            $payload['total-count-mode'] = $countMode;
        }

        if ($requestType === 'compressed GET') {
            $compressed = gzencode(json_encode($payload, \JSON_THROW_ON_ERROR));
            static::assertNotFalse($compressed);
            $request = new Request([
                '_criteria' => Base64::urlEncode($compressed),
                'total-count-mode' => $countMode === null ? 'none' : 'exact',
            ]);
        } elseif ($requestType === Request::METHOD_POST) {
            $request = new Request(
                query: ['total-count-mode' => $countMode === null ? 'none' : 'exact'],
                request: $payload,
            );
            $request->setMethod(Request::METHOD_POST);
        } else {
            $request = new Request(
                query: $payload,
                request: ['total-count-mode' => $countMode === null ? 'none' : 'exact'],
            );
        }

        $criteria = $builder->handleRequest($request, new Criteria(), $definition, $context->getContext());
        $route = new BlogReviewRoute(
            new StaticEntityRepository([new BlogReviewCollection()]),
            $this->config,
            $this->cacheTagCollector,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $result = $route->load(Uuid::randomHex(), $request, $context, $criteria)->getResult();

        static::assertSame(10, $result->getCriteria()->getLimit());
        static::assertSame(20, $result->getCriteria()->getOffset());
        static::assertSame($expectedMode, $result->getCriteria()->getTotalCountMode());
    }

    /**
     * @return iterable<string, array{string, int|string|null, int}>
     */
    public static function requestedCountModeProvider(): iterable
    {
        foreach ([Request::METHOD_GET, Request::METHOD_POST, 'compressed GET'] as $requestType) {
            yield $requestType . ' without count mode defaults to exact' => [$requestType, null, Criteria::TOTAL_COUNT_MODE_EXACT];
            yield $requestType . ' preserves disabled counting' => [$requestType, 'none', Criteria::TOTAL_COUNT_MODE_NONE];
            yield $requestType . ' preserves exact counting' => [$requestType, 'exact', Criteria::TOTAL_COUNT_MODE_EXACT];
            yield $requestType . ' preserves next-page counting' => [$requestType, 'next-pages', Criteria::TOTAL_COUNT_MODE_NEXT_PAGES];
            yield $requestType . ' preserves numeric disabled counting' => [$requestType, 0, Criteria::TOTAL_COUNT_MODE_NONE];
            yield $requestType . ' preserves numeric next-page counting' => [$requestType, 2, Criteria::TOTAL_COUNT_MODE_NEXT_PAGES];
        }
    }

    public function testLoadKeepsExplicitRequestLimit(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn('test');
        $channelContext->method('getContext')->willReturn(Context::createDefaultContext());

        // Explicit limit in the request: no STATE_NO_EXPLICIT_LIMIT_IN_REQUEST state.
        $criteria = new Criteria();
        $criteria->setLimit(25);

        $searchedCriteria = null;
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use (&$searchedCriteria): EntitySearchResult {
                $searchedCriteria = $criteria;

                return new EntitySearchResult(
                    'blog_review',
                    0,
                    new BlogReviewCollection(),
                    null,
                    $criteria,
                    Context::createDefaultContext()
                );
            });

        $this->createRoute($repository)->load(Uuid::randomHex(), new Request(), $channelContext, $criteria);

        static::assertInstanceOf(Criteria::class, $searchedCriteria);
        static::assertSame(25, $searchedCriteria->getLimit());
        // An explicit request limit is left untouched, so the total count mode is not forced either.
        static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $searchedCriteria->getTotalCountMode());
    }

    public function testLoadCapsConfiguredReviewsPerPageToStoreApiMaxLimit(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn('testReviewPerPageAboveMaxLimit');
        $channelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->setLimit(100);
        $criteria->addState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST);

        $searchedCriteria = null;
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use (&$searchedCriteria): EntitySearchResult {
                $searchedCriteria = $criteria;

                return new EntitySearchResult(
                    'blog_review',
                    0,
                    new BlogReviewCollection(),
                    null,
                    $criteria,
                    Context::createDefaultContext()
                );
            });

        $this->createRoute($repository)->load(Uuid::randomHex(), new Request(), $channelContext, $criteria);

        static::assertInstanceOf(Criteria::class, $searchedCriteria);
        static::assertSame(100, $searchedCriteria->getLimit());
        static::assertFalse($searchedCriteria->hasState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST));
    }

    public function testLoadReviewDeactivated(): void
    {
        $this->expectExceptionObject(BlogException::reviewNotActive());

        $channelContext = $this->createMock(ChannelContext::class);
        $channelContext->expects($this->exactly(2))->method('getChannelId')->willReturn('testReviewNotActive');

        $this->route->load(
            Uuid::randomHex(),
            new Request(),
            $channelContext,
            new Criteria(),
        );
    }

    public function testPublishesExtension(): void
    {
        $blogId = Uuid::randomHex();
        $request = new Request();
        $context = Generator::generateChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(BlogReviewRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('blog-review-route.load.pre', static function (BlogReviewRouteExtension $extension) use ($blogId, $request, $context, $criteria, $response): void {
            static::assertSame(['blogId' => $blogId, 'request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new BlogReviewRoute(
            static::createStub(EntityRepository::class),
            static::createStub(SystemConfigService::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($blogId, $request, $context, $criteria));
    }

    public function testExtensionCanOverrideConfiguredPagination(): void
    {
        $context = static::createStub(ChannelContext::class);
        $context->method('getChannelId')->willReturn('test');
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->setLimit(100);
        $criteria->setOffset(100);
        $criteria->addState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(BlogReviewRouteExtension::onPre(), static function (BlogReviewRouteExtension $extension): void {
            static::assertSame(10, $extension->criteria->getLimit());
            static::assertSame(10, $extension->criteria->getOffset());
            static::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $extension->criteria->getTotalCountMode());
            static::assertFalse($extension->criteria->hasState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST));

            $extension->criteria->setLimit(5);
            $extension->criteria->setOffset(5);
        });

        $route = new BlogReviewRoute(
            new StaticEntityRepository([new BlogReviewCollection()]),
            $this->config,
            $this->cacheTagCollector,
            new ExtensionDispatcher($dispatcher),
        );

        $response = $route->load(Uuid::randomHex(), new Request(), $context, $criteria);

        static::assertSame(5, $response->getResult()->getCriteria()->getLimit());
        static::assertSame(5, $response->getResult()->getCriteria()->getOffset());
    }

    /**
     * @param (EntityRepository<BlogReviewCollection>&MockObject)|null $repository
     */
    private function createRoute(
        ?EntityRepository $repository = null,
        ?CacheTagCollector $cacheTagCollector = null,
    ): BlogReviewRoute {
        return new BlogReviewRoute(
            $repository ?? $this->repository,
            $this->config,
            $cacheTagCollector ?? $this->cacheTagCollector,
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }
}
