<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Breadcrumb\ContentSystem\DataLoader;

use Contena\Core\Content\Breadcrumb\BreadcrumbException;
use Contena\Core\Content\Breadcrumb\Channel\AbstractBreadcrumbRoute;
use Contena\Core\Content\Breadcrumb\Channel\BreadcrumbRouteResponse;
use Contena\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbDataLoader;
use Contena\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbLoaderConfig;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
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
#[CoversClass(BreadcrumbDataLoader::class)]
class BreadcrumbDataLoaderTest extends TestCase
{
    private AbstractBreadcrumbRoute&Stub $breadcrumbRoute;

    private BreadcrumbDataLoader $loader;

    private ?Request $capturedRequest = null;

    protected function setUp(): void
    {
        $this->capturedRequest = null;

        $response = new BreadcrumbRouteResponse(new BreadcrumbCollection());

        $this->breadcrumbRoute = static::createStub(AbstractBreadcrumbRoute::class);
        $this->breadcrumbRoute
            ->method('load')
            ->willReturnCallback(function (Request $request) use ($response): BreadcrumbRouteResponse {
                $this->capturedRequest = $request;

                return $response;
            });

        $this->loader = new BreadcrumbDataLoader($this->breadcrumbRoute);
    }

    #[TestDox('returns breadcrumb as requirement type identifier')]
    public function testGetRequirementTypeReturnsBreadcrumbString(): void
    {
        static::assertSame('breadcrumb', BreadcrumbDataLoader::getRequirementType());
    }

    #[TestDox('declares BreadcrumbCollection as its single producible type')]
    public function testProducibleTypesReturnsSingleBreadcrumbCapability(): void
    {
        $capabilities = $this->loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(BreadcrumbCollection::class, $capabilities[0]->producedType);
        static::assertSame([], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
    }

    #[TestDox('returns breadcrumb collection as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithBreadcrumbData(): void
    {
        $breadcrumbCollection = new BreadcrumbCollection();
        $response = new BreadcrumbRouteResponse($breadcrumbCollection);

        $breadcrumbRoute = static::createStub(AbstractBreadcrumbRoute::class);
        $breadcrumbRoute->method('load')->willReturn($response);

        $loader = new BreadcrumbDataLoader($breadcrumbRoute);
        $result = $loader->load(
            self::inputs(Uuid::randomHex()),
            self::requirement(),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertSame($breadcrumbCollection, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('sets entity ID on cloned request attributes and type on query params')]
    public function testLoadSetsIdAndTypeOnClonedRequest(): void
    {
        $entityId = Uuid::randomHex();

        $this->loader->load(
            self::inputs($entityId, type: 'category'),
            self::requirement(),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Request::class, $this->capturedRequest);
        static::assertSame($entityId, $this->capturedRequest->attributes->get('id'));
        static::assertSame('category', $this->capturedRequest->query->get('type'));
    }

    #[TestDox('reads entity ID from the element property the config names')]
    public function testLoadReadsEntityIdFromCustomProperty(): void
    {
        $categoryId = Uuid::randomHex();

        $inputs = $this->resolve(
            new BreadcrumbLoaderConfig(property: 'categoryId'),
            ['categoryId' => $categoryId],
        );

        $this->loader->load(
            $inputs,
            self::requirement(),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Request::class, $this->capturedRequest);
        static::assertSame($categoryId, $this->capturedRequest->attributes->get('id'));
    }

    #[TestDox('resolves an unset property to the declared entityId default')]
    public function testUnsetPropertyResolvesToDeclaredEntityIdDefault(): void
    {
        $entityId = Uuid::randomHex();

        $inputs = $this->resolve(new BreadcrumbLoaderConfig(), ['entityId' => $entityId]);

        $this->loader->load(
            $inputs,
            self::requirement(),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Request::class, $this->capturedRequest);
        static::assertSame($entityId, $this->capturedRequest->attributes->get('id'));
    }

    #[TestDox('sets lowercased referrerCategoryId on cloned request when the referrer input is resolved')]
    public function testLoadSetsReferrerCategoryIdOnRequest(): void
    {
        $referrerCategoryId = Uuid::randomHex();

        $this->loader->load(
            self::inputs(Uuid::randomHex(), referrerCategoryProperty: strtoupper($referrerCategoryId)),
            self::requirement(),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Request::class, $this->capturedRequest);
        static::assertSame($referrerCategoryId, $this->capturedRequest->query->get('referrerCategoryId'));
    }

    #[TestDox('does not set referrerCategoryId when the referrer input is unresolved')]
    public function testLoadDoesNotSetReferrerCategoryIdWhenReferrerInputIsUnresolved(): void
    {
        $this->loader->load(
            self::inputs(Uuid::randomHex()),
            self::requirement(),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Request::class, $this->capturedRequest);
        static::assertFalse($this->capturedRequest->query->has('referrerCategoryId'));
    }

    #[TestDox('lowercases entity ID before passing it to the breadcrumb route')]
    public function testLoadCallsBreadcrumbRouteWithLowercasedEntityId(): void
    {
        $entityId = Uuid::randomHex();

        $this->loader->load(
            self::inputs(strtoupper($entityId)),
            self::requirement(),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Request::class, $this->capturedRequest);
        static::assertSame($entityId, $this->capturedRequest->attributes->get('id'));
    }

    #[DataProvider('invalidEntityIdInputProvider')]
    #[TestDox('returns notFound result without calling the breadcrumb route when $_dataName')]
    public function testLoadReturnsNotFoundForInvalidInput(LoaderInputs $inputs): void
    {
        $breadcrumbRoute = $this->createMock(AbstractBreadcrumbRoute::class);
        $breadcrumbRoute->expects($this->never())->method('load');
        $loader = new BreadcrumbDataLoader($breadcrumbRoute);

        $result = $loader->load(
            $inputs,
            self::requirement(),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[DataProvider('sampleDomainExceptionProvider')]
    #[TestDox('degrades to notFound when the breadcrumb route throws the Contena exception $_dataName')]
    public function testLoadReturnsNotFoundWhenBreadcrumbRouteThrows(\Throwable $exception): void
    {
        $breadcrumbRoute = $this->createMock(AbstractBreadcrumbRoute::class);
        $breadcrumbRoute
            ->expects($this->once())
            ->method('load')
            ->willThrowException($exception);

        $loader = new BreadcrumbDataLoader($breadcrumbRoute);
        $result = $loader->load(
            self::inputs(Uuid::randomHex()),
            self::requirement(),
            Generator::generateChannelContext(),
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lets a TypeError from the breadcrumb route propagate instead of degrading')]
    public function testLoadLetsThrowableOutsideContenaHttpExceptionPropagate(): void
    {
        $typeError = new \TypeError('Argument #2 ($channelContext) must be of type ChannelContext, null given');

        $breadcrumbRoute = static::createStub(AbstractBreadcrumbRoute::class);
        $breadcrumbRoute
            ->method('load')
            ->willThrowException($typeError);

        $loader = new BreadcrumbDataLoader($breadcrumbRoute);

        try {
            $loader->load(
                self::inputs(Uuid::randomHex()),
                self::requirement(),
                Generator::generateChannelContext(),
                new Request(),
            );

            static::fail('Expected the TypeError to propagate out of load() instead of degrading to notFound');
        } catch (\TypeError $caught) {
            static::assertSame($typeError, $caught);
        }
    }

    /**
     * Sample domain exceptions off the breadcrumb chain, not one row per catch arm: the loader catches the
     * single covering ancestor `ContenaHttpException`, so no row maps to a clause of its own.
     *
     * @return iterable<string, array{\Throwable}>
     */
    public static function sampleDomainExceptionProvider(): iterable
    {
        // CategoryBreadcrumbBuilder::getBlogBreadcrumbUrls() throws this when the blog resolves to no
        // category. BreadcrumbException extends CategoryException, which extends HttpException, which
        // extends ContenaHttpException.
        yield 'no main category for the blog' => [
            BreadcrumbException::categoryNotFoundForBlog('blog-missing'),
        ];

        // CategoryBreadcrumbBuilder::loadBlog() throws this (:191). The factory returns
        // BlogNotFoundException, which extends ContenaHttpException directly rather than through
        // BreadcrumbException. BreadcrumbRoute catches it only on its blog branch, so the category branch
        // lets it out.
        yield 'the blog behind the breadcrumb is missing' => [
            BreadcrumbException::blogNotFound('blog-missing'),
        ];

        // Not a reachability claim: this row pins the clause to the ancestor rather than to the chain's own
        // classes, using a class the breadcrumb chain does not produce.
        yield 'a class outside the chain that extends ContenaHttpException directly' => [
            new DecorationPatternException(AbstractBreadcrumbRoute::class),
        ];
    }

    /**
     * @return iterable<string, array{LoaderInputs}>
     */
    public static function invalidEntityIdInputProvider(): iterable
    {
        yield 'the entity ID input is unresolved' => [
            self::inputs(null),
        ];

        yield 'the resolved entity ID is not a valid uuid' => [
            self::inputs('{{blogId}}'),
        ];

        // The entity ID is a valid uuid, so only the referrer can carry this row to notFound.
        yield 'a resolved referrer category ID is not a valid uuid' => [
            self::inputs(Uuid::randomHex(), referrerCategoryProperty: '{{categoryId}}'),
        ];
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function resolve(BreadcrumbLoaderConfig $config, array $properties): LoaderInputs
    {
        return new LoaderInputResolver()->resolve($this->loader->configSpecification(), $config, $properties);
    }

    private static function inputs(
        ?string $property,
        string $type = 'blog',
        ?string $referrerCategoryProperty = null,
    ): LoaderInputs {
        return new LoaderInputs([
            'property' => $property,
            'type' => $type,
            'referrerCategoryProperty' => $referrerCategoryProperty,
        ]);
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('breadcrumb', 'breadcrumb', new BreadcrumbLoaderConfig());
    }
}
