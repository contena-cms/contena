<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Search\AbstractBlogSearchRoute;
use Contena\Core\Content\Blog\Channel\Search\BlogSearchRouteResponse;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSearchDataLoader;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSearchLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Script\ScriptException;
use Contena\Core\Test\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogSearchDataLoader::class)]
class BlogSearchDataLoaderTest extends TestCase
{
    private AbstractBlogSearchRoute&Stub $searchRoute;

    private BlogSearchDataLoader $loader;

    protected function setUp(): void
    {
        $this->searchRoute = static::createStub(AbstractBlogSearchRoute::class);
        $this->loader = new BlogSearchDataLoader($this->searchRoute);
    }

    #[TestDox('returns blog_search as requirement type identifier')]
    public function testGetRequirementTypeReturnsBlogSearchString(): void
    {
        static::assertSame('blog_search', BlogSearchDataLoader::getRequirementType());
    }

    #[TestDox('declares BlogListingResult as its single producible type')]
    public function testProducibleTypesDeclaresExtendsType(): void
    {
        $capabilities = $this->loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(BlogListingResult::class, $capabilities[0]->producedType);
        static::assertSame([], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
    }

    #[TestDox('returns search listing result as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithSearchData(): void
    {
        $context = Generator::generateChannelContext();
        $request = new Request();

        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogSearchRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        $this->searchRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->loader->load(
            new LoaderInputs(['searchTermProperty' => 'shoes', 'associations' => []]),
            self::requirement(),
            $context,
            $request,
        );

        static::assertSame($listingResult, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('sets search term on cloned request POST body for route consumption')]
    public function testLoadSetsSearchTermOnClonedRequestBody(): void
    {
        $context = Generator::generateChannelContext();
        $request = new Request();

        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogSearchRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->searchRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): BlogSearchRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $this->loader->load(
            new LoaderInputs(['searchTermProperty' => 'running shoes', 'associations' => []]),
            self::requirement(),
            $context,
            $request,
        );

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('running shoes', $capturedRequest->request->get('search'));
        static::assertNotSame($request, $capturedRequest);
    }

    #[TestDox('does not leak original request query parameters into the route request')]
    public function testLoadDoesNotLeakOriginalRequestQueryParams(): void
    {
        $context = Generator::generateChannelContext();
        $request = new Request(['limit' => '24', 'p' => '3', 'order' => 'price-asc']);

        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogSearchRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->searchRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): BlogSearchRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $this->loader->load(
            new LoaderInputs(['searchTermProperty' => 'shoes', 'associations' => []]),
            self::requirement(),
            $context,
            $request,
        );

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('shoes', $capturedRequest->request->get('search'));
        static::assertSame([], $capturedRequest->query->all());
    }

    #[TestDox('dereferences the element property the config names into the search term')]
    public function testLoadUsesCustomSearchTermPropertyFromConfig(): void
    {
        $context = Generator::generateChannelContext();

        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogSearchRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->searchRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): BlogSearchRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $inputs = $this->resolve(
            new BlogSearchLoaderConfig(searchTermProperty: 'query'),
            ['query' => 'blue shirt'],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('blue shirt', $capturedRequest->request->get('search'));
    }

    #[TestDox('resolves an unset searchTermProperty to the declared searchTerm default')]
    public function testUnsetSearchTermPropertyResolvesToDeclaredSearchTermDefault(): void
    {
        $context = Generator::generateChannelContext();

        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogSearchRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->searchRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): BlogSearchRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $inputs = $this->resolve(
            new BlogSearchLoaderConfig(),
            ['searchTerm' => 'winter jacket'],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('winter jacket', $capturedRequest->request->get('search'));
    }

    #[TestDox('adds every configured association to the criteria')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $context = Generator::generateChannelContext();

        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogSearchRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $this->searchRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): BlogSearchRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load(
            new LoaderInputs(['searchTermProperty' => 'shoes', 'associations' => ['manufacturer', 'cover']]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame(['manufacturer', 'cover'], array_keys($capturedCriteria->getAssociations()));
    }

    #[TestDox('appends the associations element property after the configured associations by default')]
    public function testLoadMergesElementAssociationsIntoCriteria(): void
    {
        $context = Generator::generateChannelContext();

        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogSearchRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $this->searchRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): BlogSearchRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $inputs = $this->resolve(
            new BlogSearchLoaderConfig(associations: ['manufacturer']),
            ['searchTerm' => 'winter jacket', 'associations' => ['cover', 'media']],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame(['manufacturer', 'cover', 'media'], array_keys($capturedCriteria->getAssociations()));
    }

    #[TestDox('returns notFound result when the search term resolves to an empty string')]
    public function testLoadReturnsNotFoundWhenSearchTermIsEmptyString(): void
    {
        $context = Generator::generateChannelContext();

        $searchRoute = $this->createMock(AbstractBlogSearchRoute::class);
        $searchRoute->expects($this->never())->method('load');

        $loader = new BlogSearchDataLoader($searchRoute);
        $result = $loader->load(
            new LoaderInputs(['searchTermProperty' => '', 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when the search term input is unresolved')]
    public function testLoadReturnsNotFoundWhenSearchTermInputIsUnresolved(): void
    {
        $context = Generator::generateChannelContext();

        $searchRoute = $this->createMock(AbstractBlogSearchRoute::class);
        $searchRoute->expects($this->never())->method('load');

        $loader = new BlogSearchDataLoader($searchRoute);
        $result = $loader->load(
            new LoaderInputs(['searchTermProperty' => null, 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[DataProvider('sampleDomainExceptionProvider')]
    #[TestDox('degrades to notFound when the search route throws the Contena exception $_dataName')]
    public function testLoadReturnsNotFoundWhenSearchRouteThrows(\Throwable $exception): void
    {
        $context = Generator::generateChannelContext();

        $searchRoute = $this->createMock(AbstractBlogSearchRoute::class);
        $searchRoute
            ->expects($this->once())
            ->method('load')
            ->willThrowException($exception);

        $loader = new BlogSearchDataLoader($searchRoute);
        $result = $loader->load(
            new LoaderInputs(['searchTermProperty' => 'shoes', 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lets a TypeError from the search route propagate instead of degrading')]
    public function testLoadLetsThrowableOutsideContenaHttpExceptionPropagate(): void
    {
        $context = Generator::generateChannelContext();

        $typeError = new \TypeError('Argument #3 ($criteria) must be of type Criteria, null given');

        $searchRoute = static::createStub(AbstractBlogSearchRoute::class);
        $searchRoute
            ->method('load')
            ->willThrowException($typeError);

        $loader = new BlogSearchDataLoader($searchRoute);

        try {
            $loader->load(
                new LoaderInputs(['searchTermProperty' => 'shoes', 'associations' => []]),
                self::requirement(),
                $context,
                new Request(),
            );

            static::fail('Expected the TypeError to propagate out of load() instead of degrading to notFound');
        } catch (\TypeError $caught) {
            static::assertSame($typeError, $caught);
        }
    }

    /**
     * Sample domain exceptions off the search chain, not one row per catch arm: the loader catches the
     * single covering ancestor `ContenaHttpException`, so no row maps to a clause of its own.
     *
     * @return iterable<string, array{\Throwable}>
     */
    public static function sampleDomainExceptionProvider(): iterable
    {
        // Reachable via CompositeListingProcessor::prepare() -> SortingListingProcessor::prepare() when
        // the configured default sorting id points to a deleted sorting entity; SortingListingProcessor
        // itself calls the factory with an empty key. Not flag-dependent.
        yield 'default sorting entity missing' => [BlogException::sortingNotFoundException('')];

        // Flag-off form of BlogException::missingRequestParameter('search'), thrown directly rather than
        // via the factory so this row holds regardless of v6.8.0.0 state.
        yield 'missing search parameter, flag-off form' => [RoutingException::missingRequestParameter('search')];

        // AppScriptBlogPriceCalculator decorates BlogPriceCalculator on the search chain, and
        // ScriptExecutor rewraps any Throwable an app script raises into ScriptExecutionFailedException, so
        // no enumeration of the chain's own exception classes can cover it.
        yield 'app script failure rewrapped as ScriptExecutionFailedException' => [
            ScriptException::scriptExecutionFailed('blog-pricing', 'blog-pricing.twig', new \RuntimeException('app script failed')),
        ];
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function resolve(BlogSearchLoaderConfig $config, array $properties): LoaderInputs
    {
        return new LoaderInputResolver()->resolve($this->loader->configSpecification(), $config, $properties);
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('search', 'blog_search', new BlogSearchLoaderConfig());
    }
}
