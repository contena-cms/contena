<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Search\AbstractBlogSearchRoute;
use Contena\Core\Content\Blog\Channel\Search\BlogSearchRouteResponse;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSearchDataLoader;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSearchLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\ContentSystem\ContentElementBuilder;
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
        $config = new BlogSearchLoaderConfig();
        $requirement = new DataRequirement('search', 'blog_search', $config);
        $element = ContentElementBuilder::create('search')
            ->withProperty('searchTerm', 'content')
            ->build();
        $context = Generator::generateChannelContext();
        $request = new Request();

        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogSearchRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        $this->searchRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->loader->load($element, $requirement, $context, $request);

        static::assertSame($listingResult, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('sets search term on cloned request POST body for route consumption')]
    public function testLoadSetsSearchTermOnClonedRequestBody(): void
    {
        $config = new BlogSearchLoaderConfig();
        $requirement = new DataRequirement('search', 'blog_search', $config);
        $element = ContentElementBuilder::create('search')
            ->withProperty('searchTerm', 'content system')
            ->build();
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

        $this->loader->load($element, $requirement, $context, $request);

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('content system', $capturedRequest->request->get('search'));
        static::assertNotSame($request, $capturedRequest);
    }

    #[TestDox('does not leak original request query parameters into the route request')]
    public function testLoadDoesNotLeakOriginalRequestQueryParams(): void
    {
        $config = new BlogSearchLoaderConfig();
        $requirement = new DataRequirement('search', 'blog_search', $config);
        $element = ContentElementBuilder::create('search')
            ->withProperty('searchTerm', 'content')
            ->build();
        $context = Generator::generateChannelContext();
        $request = new Request(['limit' => '24', 'p' => '3', 'order' => 'created-at-desc']);

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

        $this->loader->load($element, $requirement, $context, $request);

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('content', $capturedRequest->request->get('search'));
        static::assertSame([], $capturedRequest->query->all());
    }

    #[TestDox('reads search term from custom property name when configured')]
    public function testLoadUsesCustomSearchTermPropertyFromConfig(): void
    {
        $config = new BlogSearchLoaderConfig(searchTermProperty: 'query');
        $requirement = new DataRequirement('search', 'blog_search', $config);
        $element = ContentElementBuilder::create('search')
            ->withProperty('query', 'platform update')
            ->build();
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

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('platform update', $capturedRequest->request->get('search'));
    }

    #[TestDox('adds config associations to criteria when loading search')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $config = new BlogSearchLoaderConfig(associations: ['tags', 'cover']);
        $requirement = new DataRequirement('search', 'blog_search', $config);
        $element = ContentElementBuilder::create('search')
            ->withProperty('searchTerm', 'content')
            ->build();
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

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('tags', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
    }

    #[TestDox('merges element associations property into criteria when it is an array of strings')]
    public function testLoadMergesElementAssociationsIntoCriteria(): void
    {
        $config = new BlogSearchLoaderConfig(associations: ['tags']);
        $requirement = new DataRequirement('search', 'blog_search', $config);
        $element = ContentElementBuilder::create('search')
            ->withProperty('searchTerm', 'content')
            ->withProperty('associations', ['cover', 'media'])
            ->build();
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

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('tags', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
    }

    #[TestDox('returns notFound result when config is not a BlogSearchLoaderConfig instance')]
    public function testLoadReturnsNotFoundWhenConfigIsWrongType(): void
    {
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('search', 'blog_search', $wrongConfig);
        $element = ContentElementBuilder::create('search')->build();
        $context = Generator::generateChannelContext();

        $searchRoute = $this->createMock(AbstractBlogSearchRoute::class);
        $searchRoute->expects($this->never())->method('load');

        $loader = new BlogSearchDataLoader($searchRoute);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when search term element property is an empty string')]
    public function testLoadReturnsNotFoundWhenSearchTermPropertyIsEmptyString(): void
    {
        $config = new BlogSearchLoaderConfig();
        $element = ContentElementBuilder::create('search')
            ->withProperty('searchTerm', '')
            ->build();
        $context = Generator::generateChannelContext();

        $searchRoute = $this->createMock(AbstractBlogSearchRoute::class);
        $searchRoute->expects($this->never())->method('load');

        $loader = new BlogSearchDataLoader($searchRoute);
        $result = $loader->load(
            $element,
            new DataRequirement('search', 'blog_search', $config),
            $context,
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
    }

    #[DataProvider('guardsInvalidSearchTermProvider')]
    #[TestDox('returns notFound result when searchTerm is invalid: $_dataName')]
    public function testLoadReturnsNotFoundWhenSearchTermPropertyIsInvalid(ContentElement $element): void
    {
        $config = new BlogSearchLoaderConfig();
        $context = Generator::generateChannelContext();

        $searchRoute = $this->createMock(AbstractBlogSearchRoute::class);
        $searchRoute->expects($this->never())->method('load');

        $loader = new BlogSearchDataLoader($searchRoute);
        $result = $loader->load(
            $element,
            new DataRequirement('search', 'blog_search', $config),
            $context,
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * @return iterable<string, array{ContentElement}>
     */
    public static function guardsInvalidSearchTermProvider(): iterable
    {
        yield 'non-string value triggers guard' => [
            ContentElementBuilder::create('search')->withProperty('searchTerm', 42)->build(),
        ];
        yield 'missing property triggers guard' => [
            ContentElementBuilder::create('search')->build(),
        ];
    }
}
