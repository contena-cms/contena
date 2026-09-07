<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Listing\AbstractBlogListingRoute;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRouteResponse;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogListingDataLoader;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogListingLoaderConfig;
use Contena\Core\Content\BlogStream\BlogStreamException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Script\ScriptException;
use Contena\Core\Framework\Uuid\Uuid;
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
#[CoversClass(BlogListingDataLoader::class)]
class BlogListingDataLoaderTest extends TestCase
{
    private AbstractBlogListingRoute&Stub $listingRoute;

    private BlogListingDataLoader $loader;

    protected function setUp(): void
    {
        $this->listingRoute = static::createStub(AbstractBlogListingRoute::class);
        $this->loader = new BlogListingDataLoader($this->listingRoute);
    }

    #[TestDox('returns blog_listing as requirement type identifier')]
    public function testGetRequirementTypeReturnsBlogListingString(): void
    {
        static::assertSame('blog_listing', BlogListingDataLoader::getRequirementType());
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

    #[TestDox('returns listing result as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithListingData(): void
    {
        $navigationId = Uuid::randomHex();

        $context = Generator::generateChannelContext();
        $request = new Request();

        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $listingRoute = $this->createMock(AbstractBlogListingRoute::class);
        $listingRoute
            ->expects($this->once())
            ->method('load')
            ->with($navigationId, $request, $context, static::isInstanceOf(Criteria::class))
            ->willReturn($response);

        $loader = new BlogListingDataLoader($listingRoute);
        $result = $loader->load(
            new LoaderInputs(['property' => $navigationId, 'associations' => []]),
            self::requirement(),
            $context,
            $request,
        );

        static::assertSame($listingResult, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lowercases navigationId before passing it to the listing route')]
    public function testLoadCallsListingRouteWithLowercasedNavigationId(): void
    {
        $navigationId = Uuid::randomHex();
        $upperCaseId = strtoupper($navigationId);

        $context = Generator::generateChannelContext();

        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $capturedNavigationId = null;
        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId) use (&$capturedNavigationId, $response): BlogListingRouteResponse {
                $capturedNavigationId = $catId;

                return $response;
            });

        $this->loader->load(
            new LoaderInputs(['property' => $upperCaseId, 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertSame($navigationId, $capturedNavigationId);
    }

    #[TestDox('dereferences the element property the config names into the navigation ID')]
    public function testLoadUsesCustomPropertyNameFromConfig(): void
    {
        $context = Generator::generateChannelContext();
        $categoryId = Uuid::randomHex();

        $capturedCategoryId = null;
        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId) use (&$capturedCategoryId, $response): BlogListingRouteResponse {
                $capturedCategoryId = $catId;

                return $response;
            });

        $inputs = $this->resolve(
            new BlogListingLoaderConfig(property: 'categoryId'),
            ['categoryId' => $categoryId],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertSame($categoryId, $capturedCategoryId);
    }

    #[TestDox('resolves an unset property to the declared navigationId default')]
    public function testUnsetPropertyResolvesToDeclaredNavigationIdDefault(): void
    {
        $context = Generator::generateChannelContext();
        $navigationId = Uuid::randomHex();

        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $capturedNavigationId = null;
        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId) use (&$capturedNavigationId, $response): BlogListingRouteResponse {
                $capturedNavigationId = $catId;

                return $response;
            });

        $inputs = $this->resolve(new BlogListingLoaderConfig(), ['navigationId' => $navigationId]);
        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertSame($navigationId, $capturedNavigationId);
    }

    #[TestDox('adds every configured association to the criteria')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $navigationId = Uuid::randomHex();

        $context = Generator::generateChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): BlogListingRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load(
            new LoaderInputs(['property' => $navigationId, 'associations' => ['manufacturer', 'cover']]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame(['manufacturer', 'cover'], array_keys($capturedCriteria->getAssociations()));
    }

    #[TestDox('appends the resolved associationOverride entries after the configured associations')]
    public function testAssociationOverrideEntriesFollowConfiguredAssociationsInCriteria(): void
    {
        $context = Generator::generateChannelContext();
        $navigationId = Uuid::randomHex();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): BlogListingRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $inputs = $this->resolve(
            new BlogListingLoaderConfig(associations: ['media'], associationOverride: 'extraAssociations'),
            ['navigationId' => $navigationId, 'extraAssociations' => ['cover']],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame(['media', 'cover'], array_keys($capturedCriteria->getAssociations()));
    }

    #[TestDox('appends the associations element property after the configured associations by default')]
    public function testLoadMergesElementAssociationsIntoCriteria(): void
    {
        $context = Generator::generateChannelContext();
        $navigationId = Uuid::randomHex();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): BlogListingRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $inputs = $this->resolve(
            new BlogListingLoaderConfig(associations: ['manufacturer']),
            ['navigationId' => $navigationId, 'associations' => ['cover', 'media']],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame(['manufacturer', 'cover', 'media'], array_keys($capturedCriteria->getAssociations()));
    }

    #[TestDox('degrades an empty-string navigationId to notFound without calling the listing route')]
    public function testLoadDegradesEmptyStringNavigationIdWithoutCallingRoute(): void
    {
        $context = Generator::generateChannelContext();

        $listingRoute = $this->createMock(AbstractBlogListingRoute::class);
        $listingRoute->expects($this->never())->method('load');

        $loader = new BlogListingDataLoader($listingRoute);
        $result = $loader->load(
            new LoaderInputs(['property' => '', 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('builds a criteria carrying no associations when none are configured')]
    public function testLoadBuildsEmptyCriteriaWhenNoAssociationsConfigured(): void
    {
        $navigationId = Uuid::randomHex();

        $context = Generator::generateChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): BlogListingRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load(
            new LoaderInputs(['property' => $navigationId, 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame([], array_keys($capturedCriteria->getAssociations()));
    }

    #[TestDox('returns notFound result when the navigation ID input is unresolved')]
    public function testLoadReturnsNotFoundWhenNavigationIdInputIsUnresolved(): void
    {
        $context = Generator::generateChannelContext();

        $listingRoute = $this->createMock(AbstractBlogListingRoute::class);
        $listingRoute->expects($this->never())->method('load');

        $loader = new BlogListingDataLoader($listingRoute);
        $result = $loader->load(
            new LoaderInputs(['property' => null, 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when the resolved property is not a valid uuid')]
    public function testLoadReturnsNotFoundWhenPropertyIsNotValidUuid(): void
    {
        $context = Generator::generateChannelContext();

        $listingRoute = $this->createMock(AbstractBlogListingRoute::class);
        $listingRoute->expects($this->never())->method('load');

        $loader = new BlogListingDataLoader($listingRoute);
        $result = $loader->load(
            new LoaderInputs(['property' => '{{categoryId}}', 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[DataProvider('sampleDomainExceptionProvider')]
    #[TestDox('degrades to notFound when the listing route throws the Contena exception $_dataName')]
    public function testLoadReturnsNotFoundWhenListingRouteThrows(\Throwable $exception): void
    {
        $navigationId = Uuid::randomHex();
        $context = Generator::generateChannelContext();

        $listingRoute = $this->createMock(AbstractBlogListingRoute::class);
        $listingRoute
            ->expects($this->once())
            ->method('load')
            ->willThrowException($exception);

        $loader = new BlogListingDataLoader($listingRoute);
        $result = $loader->load(
            new LoaderInputs(['property' => $navigationId, 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lets a TypeError from the listing route propagate instead of degrading')]
    public function testLoadLetsThrowableOutsideContenaHttpExceptionPropagate(): void
    {
        $navigationId = Uuid::randomHex();
        $context = Generator::generateChannelContext();

        $typeError = new \TypeError('Argument #1 ($navigationId) must be of type string, null given');

        $listingRoute = static::createStub(AbstractBlogListingRoute::class);
        $listingRoute
            ->method('load')
            ->willThrowException($typeError);

        $loader = new BlogListingDataLoader($listingRoute);

        try {
            $loader->load(
                new LoaderInputs(['property' => $navigationId, 'associations' => []]),
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
     * Sample domain exceptions off the listing chain, not one row per catch arm: the loader catches the
     * single covering ancestor `ContenaHttpException`, so no row maps to a clause of its own.
     *
     * @return iterable<string, array{\Throwable}>
     */
    public static function sampleDomainExceptionProvider(): iterable
    {
        yield 'category not found' => [BlogException::categoryNotFound('category-missing')];

        yield 'blog stream not found' => [BlogStreamException::blogStreamNotFound('stream-missing')];

        yield 'blog stream has no filters' => [BlogStreamException::noFilters('stream-no-filters')];

        yield 'blog stream is empty' => [BlogStreamException::emptyBlogStream('stream-empty')];

        // AppScriptBlogPriceCalculator decorates BlogPriceCalculator on the listing chain, and
        // ScriptExecutor rewraps any Throwable an app script raises into ScriptExecutionFailedException, so
        // no enumeration of the chain's own exception classes can cover it.
        yield 'app script failure rewrapped as ScriptExecutionFailedException' => [
            ScriptException::scriptExecutionFailed('blog-pricing', 'blog-pricing.twig', new \RuntimeException('app script failed')),
        ];

        // This loader forwards the incoming Request, so CompressedCriteriaListingProcessor reads the
        // client-supplied `_criteria` query parameter and CompressedCriteriaDecoder rejects a malformed one.
        // The search and suggest loaders build a fresh Request, so this class is not reachable there.
        yield 'malformed compressed criteria request parameter' => [
            DataAbstractionLayerException::invalidCompressedCriteriaParameter('Invalid JSON data'),
        ];
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function resolve(BlogListingLoaderConfig $config, array $properties): LoaderInputs
    {
        return new LoaderInputResolver()->resolve($this->loader->configSpecification(), $config, $properties);
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('listing', 'blog_listing', new BlogListingLoaderConfig());
    }
}
