<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Suggest\AbstractBlogSuggestRoute;
use Contena\Core\Content\Blog\Channel\Suggest\BlogSuggestRouteResponse;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSuggestDataLoader;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSuggestLoaderConfig;
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
        $config = new BlogSuggestLoaderConfig();
        $requirement = new DataRequirement('suggest', 'blog_suggest', $config);
        $element = ContentElementBuilder::create('suggest')
            ->withProperty('searchTerm', 'content')
            ->build();
        $context = Generator::generateChannelContext();
        $request = new Request();

        $listingResult = static::createStub(BlogListingResult::class);
        $response = static::createStub(BlogSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        $this->suggestRoute
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
        $config = new BlogSuggestLoaderConfig();
        $requirement = new DataRequirement('suggest', 'blog_suggest', $config);
        $element = ContentElementBuilder::create('suggest')
            ->withProperty('searchTerm', 'content system')
            ->build();
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

        $this->loader->load($element, $requirement, $context, $request);

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('content system', $capturedRequest->request->get('search'));
        static::assertNotSame($request, $capturedRequest);
    }

    #[TestDox('does not leak original request query parameters into the route request')]
    public function testLoadDoesNotLeakOriginalRequestQueryParams(): void
    {
        $config = new BlogSuggestLoaderConfig();
        $requirement = new DataRequirement('suggest', 'blog_suggest', $config);
        $element = ContentElementBuilder::create('suggest')
            ->withProperty('searchTerm', 'content')
            ->build();
        $context = Generator::generateChannelContext();
        $request = new Request(['limit' => '24', 'p' => '3', 'order' => 'created-at-desc']);

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

        $this->loader->load($element, $requirement, $context, $request);

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('content', $capturedRequest->request->get('search'));
        static::assertSame([], $capturedRequest->query->all());
    }

    #[TestDox('reads search term from custom property name when configured')]
    public function testLoadUsesCustomSearchTermPropertyFromConfig(): void
    {
        $config = new BlogSuggestLoaderConfig(searchTermProperty: 'query');
        $requirement = new DataRequirement('suggest', 'blog_suggest', $config);
        $element = ContentElementBuilder::create('suggest')
            ->withProperty('query', 'platform update')
            ->build();
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

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('platform update', $capturedRequest->request->get('search'));
    }

    #[TestDox('adds config associations to criteria when loading suggestions')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $config = new BlogSuggestLoaderConfig(associations: ['tags', 'cover']);
        $requirement = new DataRequirement('suggest', 'blog_suggest', $config);
        $element = ContentElementBuilder::create('suggest')
            ->withProperty('searchTerm', 'content')
            ->build();
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

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('tags', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
    }

    #[TestDox('merges element associations property into criteria when it is an array of strings')]
    public function testLoadMergesElementAssociationsIntoCriteria(): void
    {
        $config = new BlogSuggestLoaderConfig(associations: ['tags']);
        $requirement = new DataRequirement('suggest', 'blog_suggest', $config);
        $element = ContentElementBuilder::create('suggest')
            ->withProperty('searchTerm', 'content')
            ->withProperty('associations', ['cover', 'media'])
            ->build();
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

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('tags', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
    }

    #[TestDox('returns notFound result when config is not a BlogSuggestLoaderConfig instance')]
    public function testLoadReturnsNotFoundWhenConfigIsWrongType(): void
    {
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('suggest', 'blog_suggest', $wrongConfig);
        $element = ContentElementBuilder::create('suggest')->build();
        $context = Generator::generateChannelContext();

        $suggestRoute = $this->createMock(AbstractBlogSuggestRoute::class);
        $suggestRoute->expects($this->never())->method('load');

        $loader = new BlogSuggestDataLoader($suggestRoute);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when search term element property is an empty string')]
    public function testLoadReturnsNotFoundWhenSearchTermPropertyIsEmptyString(): void
    {
        $config = new BlogSuggestLoaderConfig();
        $element = ContentElementBuilder::create('suggest')
            ->withProperty('searchTerm', '')
            ->build();
        $context = Generator::generateChannelContext();

        $suggestRoute = $this->createMock(AbstractBlogSuggestRoute::class);
        $suggestRoute->expects($this->never())->method('load');

        $loader = new BlogSuggestDataLoader($suggestRoute);
        $result = $loader->load(
            $element,
            new DataRequirement('suggest', 'blog_suggest', $config),
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
        $config = new BlogSuggestLoaderConfig();
        $context = Generator::generateChannelContext();

        $suggestRoute = $this->createMock(AbstractBlogSuggestRoute::class);
        $suggestRoute->expects($this->never())->method('load');

        $loader = new BlogSuggestDataLoader($suggestRoute);
        $result = $loader->load(
            $element,
            new DataRequirement('suggest', 'blog_suggest', $config),
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
            ContentElementBuilder::create('suggest')->withProperty('searchTerm', 42)->build(),
        ];
        yield 'missing property triggers guard' => [
            ContentElementBuilder::create('suggest')->build(),
        ];
    }
}
