<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Suggest\AbstractBlogSuggestRoute;
use Contena\Core\Content\Blog\Channel\Suggest\BlogSuggestRouteResponse;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSuggestDataLoader;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSuggestLoaderConfig;
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
#[CoversClass(BlogSuggestDataLoader::class)]
class BlogSuggestDataLoaderTest extends TestCase
{
    private AbstractBlogSuggestRoute&Stub $suggestRoute;

    private BlogSuggestDataLoader $loader;

    protected function setUp(): void
    {
        $this->suggestRoute = static::createStub(AbstractBlogSuggestRoute::class);
        $this->loader = new BlogSuggestDataLoader($this->suggestRoute);
    }

    #[TestDox('returns blog_suggest as requirement type identifier')]
    public function testGetRequirementTypeReturnsBlogSuggestString(): void
    {
        static::assertSame('blog_suggest', BlogSuggestDataLoader::getRequirementType());
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

    #[TestDox('returns suggest listing result as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithSuggestData(): void
    {
        $context = Generator::generateChannelContext();
        $request = new Request();

        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        $this->suggestRoute
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
        $response = static::createStub(BlogSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): BlogSuggestRouteResponse {
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
        $response = static::createStub(BlogSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): BlogSuggestRouteResponse {
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
        $response = static::createStub(BlogSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): BlogSuggestRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $inputs = $this->resolve(
            new BlogSuggestLoaderConfig(searchTermProperty: 'query'),
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
        $response = static::createStub(BlogSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): BlogSuggestRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $inputs = $this->resolve(
            new BlogSuggestLoaderConfig(),
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
        $response = static::createStub(BlogSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): BlogSuggestRouteResponse {
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
        $response = static::createStub(BlogSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): BlogSuggestRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $inputs = $this->resolve(
            new BlogSuggestLoaderConfig(associations: ['manufacturer']),
            ['searchTerm' => 'winter jacket', 'associations' => ['cover', 'media']],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame(['manufacturer', 'cover', 'media'], array_keys($capturedCriteria->getAssociations()));
    }

    #[DataProvider('unusableSearchTermProvider')]
    #[TestDox('returns notFound result without calling the suggest route when the search term is $_dataName')]
    public function testLoadReturnsNotFoundWhenSearchTermIsUnusable(?string $searchTerm): void
    {
        $context = Generator::generateChannelContext();

        $suggestRoute = $this->createMock(AbstractBlogSuggestRoute::class);
        $suggestRoute->expects($this->never())->method('load');

        $loader = new BlogSuggestDataLoader($suggestRoute);
        $result = $loader->load(
            new LoaderInputs(['searchTermProperty' => $searchTerm, 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[DataProvider('sampleDomainExceptionProvider')]
    #[TestDox('degrades to notFound when the suggest route throws the Contena exception $_dataName')]
    public function testLoadReturnsNotFoundWhenSuggestRouteThrows(\Throwable $exception): void
    {
        $context = Generator::generateChannelContext();

        $suggestRoute = $this->createMock(AbstractBlogSuggestRoute::class);
        $suggestRoute
            ->expects($this->once())
            ->method('load')
            ->willThrowException($exception);

        $loader = new BlogSuggestDataLoader($suggestRoute);
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

    #[TestDox('lets a TypeError from the suggest route propagate instead of degrading')]
    public function testLoadLetsThrowableOutsideContenaHttpExceptionPropagate(): void
    {
        $context = Generator::generateChannelContext();

        $typeError = new \TypeError('Argument #3 ($criteria) must be of type Criteria, null given');

        $suggestRoute = static::createStub(AbstractBlogSuggestRoute::class);
        $suggestRoute
            ->method('load')
            ->willThrowException($typeError);

        $loader = new BlogSuggestDataLoader($suggestRoute);

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
     * @return iterable<string, array{string|null}>
     */
    public static function unusableSearchTermProvider(): iterable
    {
        yield 'resolved to an empty string' => [''];

        yield 'an unresolved input' => [null];
    }

    /**
     * Sample domain exceptions off the suggest chain, not one row per catch arm: the loader catches the
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

        // AppScriptBlogPriceCalculator decorates BlogPriceCalculator on the suggest chain, and
        // ScriptExecutor rewraps any Throwable an app script raises into ScriptExecutionFailedException, so
        // no enumeration of the chain's own exception classes can cover it.
        yield 'app script failure rewrapped as ScriptExecutionFailedException' => [
            ScriptException::scriptExecutionFailed('blog-pricing', 'blog-pricing.twig', new \RuntimeException('app script failed')),
        ];
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function resolve(BlogSuggestLoaderConfig $config, array $properties): LoaderInputs
    {
        return new LoaderInputResolver()->resolve($this->loader->configSpecification(), $config, $properties);
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('suggest', 'blog_suggest', new BlogSuggestLoaderConfig());
    }
}
