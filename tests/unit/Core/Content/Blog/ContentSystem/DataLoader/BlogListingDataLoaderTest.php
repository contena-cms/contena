<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Listing\AbstractBlogListingRoute;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRouteResponse;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogListingDataLoader;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogListingLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\ContentSystem\ContentElementBuilder;
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

        $config = new BlogListingLoaderConfig();
        $requirement = new DataRequirement('listing', 'blog_listing', $config);
        $element = ContentElementBuilder::create('blog-listing')
            ->withProperty('navigationId', $navigationId)
            ->build();
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
        $result = $loader->load($element, $requirement, $context, $request);

        static::assertSame($listingResult, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lowercases navigationId before passing it to the listing route')]
    public function testLoadCallsListingRouteWithLowercasedNavigationId(): void
    {
        $navigationId = Uuid::randomHex();
        $upperCaseId = strtoupper($navigationId);

        $config = new BlogListingLoaderConfig();
        $requirement = new DataRequirement('listing', 'blog_listing', $config);
        $element = ContentElementBuilder::create('blog-listing')
            ->withProperty('navigationId', $upperCaseId)
            ->build();
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

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertSame($navigationId, $capturedNavigationId);
    }

    #[TestDox('reads navigationId from custom property name when configured')]
    public function testLoadUsesCustomPropertyNameFromConfig(): void
    {
        $navigationId = Uuid::randomHex();

        $config = new BlogListingLoaderConfig(property: 'categoryId');
        $requirement = new DataRequirement('listing', 'blog_listing', $config);
        $element = ContentElementBuilder::create('blog-listing')
            ->withProperty('categoryId', $navigationId)
            ->build();
        $context = Generator::generateChannelContext();

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

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertSame($navigationId, $capturedCategoryId);
    }

    #[TestDox('adds config associations to criteria when loading listing')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $navigationId = Uuid::randomHex();

        $config = new BlogListingLoaderConfig(associations: ['tags', 'cover']);
        $requirement = new DataRequirement('listing', 'blog_listing', $config);
        $element = ContentElementBuilder::create('blog-listing')
            ->withProperty('navigationId', $navigationId)
            ->build();
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

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('tags', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
    }

    #[TestDox('merges element associations property into criteria when it is an array of strings')]
    public function testLoadMergesElementAssociationsIntoCriteria(): void
    {
        $navigationId = Uuid::randomHex();

        $config = new BlogListingLoaderConfig(associations: ['tags']);
        $requirement = new DataRequirement('listing', 'blog_listing', $config);
        $element = ContentElementBuilder::create('blog-listing')
            ->withProperty('navigationId', $navigationId)
            ->withProperty('associations', ['cover', 'media'])
            ->build();
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

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('tags', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
    }

    #[TestDox('ignores non-string values in element associations array when building criteria')]
    public function testLoadIgnoresNonStringValuesInElementAssociations(): void
    {
        $navigationId = Uuid::randomHex();

        $config = new BlogListingLoaderConfig();
        $requirement = new DataRequirement('listing', 'blog_listing', $config);
        $element = ContentElementBuilder::create('blog-listing')
            ->withProperty('navigationId', $navigationId)
            ->withProperty('associations', ['cover', 42, null, 'media'])
            ->build();
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

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
        static::assertCount(2, $capturedCriteria->getAssociations());
    }

    #[TestDox('returns notFound result when config is not a BlogListingLoaderConfig instance')]
    public function testLoadReturnsNotFoundWhenConfigIsWrongType(): void
    {
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('listing', 'blog_listing', $wrongConfig);
        $element = ContentElementBuilder::create('blog-listing')->build();
        $context = Generator::generateChannelContext();

        $listingRoute = $this->createMock(AbstractBlogListingRoute::class);
        $listingRoute->expects($this->never())->method('load');

        $loader = new BlogListingDataLoader($listingRoute);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when navigationId element property is not a string')]
    public function testLoadReturnsNotFoundWhenNavigationIdPropertyIsNotString(): void
    {
        $config = new BlogListingLoaderConfig(property: 'navigationId');

        $element = ContentElementBuilder::create('blog-listing')
            ->withProperty('navigationId', 42)
            ->build();

        $context = Generator::generateChannelContext();

        $listingRoute = $this->createMock(AbstractBlogListingRoute::class);
        $listingRoute->expects($this->never())->method('load');

        $loader = new BlogListingDataLoader($listingRoute);
        $result = $loader->load(
            $element,
            new DataRequirement('listing', 'blog_listing', $config),
            $context,
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when navigationId element property is missing')]
    public function testLoadReturnsNotFoundWhenNavigationIdPropertyIsMissing(): void
    {
        $config = new BlogListingLoaderConfig(property: 'navigationId');

        $element = ContentElementBuilder::create('blog-listing')->build();

        $context = Generator::generateChannelContext();

        $listingRoute = $this->createMock(AbstractBlogListingRoute::class);
        $listingRoute->expects($this->never())->method('load');

        $loader = new BlogListingDataLoader($listingRoute);
        $result = $loader->load(
            $element,
            new DataRequirement('listing', 'blog_listing', $config),
            $context,
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
    }
}
