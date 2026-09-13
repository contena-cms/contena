<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Detail;

use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogDetailRoute::class)]
class BlogDetailRouteTest extends TestCase
{
    public function testLoadSeoBreadcrumb(): void
    {
        $seoCategory = new CategoryEntity();
        $seoCategory->setId(Uuid::randomHex());

        $breadcrumb = new BreadcrumbCollection([
            new Breadcrumb('Home', Uuid::randomHex()),
            new Breadcrumb('Articles', $seoCategory->getId()),
        ]);

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->method('getBlogSeoCategory')->willReturn($seoCategory);
        $breadcrumbBuilder->expects($this->once())
            ->method('getCategoryBreadcrumbUrls')
            ->willReturn($breadcrumb);

        $result = $this->buildRoute($breadcrumbBuilder)->load('1', new Request(), Generator::generateChannelContext(), new Criteria());

        static::assertSame($breadcrumb, $result->getBlog()->getSeoBreadcrumb());
    }

    #[DataProvider('skipBreadcrumbRequestProvider')]
    public function testLoadSeoBreadcrumbIsSkipped(Request $request): void
    {
        $seoCategory = new CategoryEntity();
        $seoCategory->setId(Uuid::randomHex());

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->method('getBlogSeoCategory')->willReturn($seoCategory);
        $breadcrumbBuilder->expects($this->never())->method('getCategoryBreadcrumbUrls');

        $result = $this->buildRoute($breadcrumbBuilder)->load('1', $request, Generator::generateChannelContext(), new Criteria());

        static::assertNull($result->getBlog()->getSeoBreadcrumb());
    }

    public static function skipBreadcrumbRequestProvider(): \Generator
    {
        yield 'query parameter' => [new Request([BlogDetailRoute::SKIP_BREADCRUMB => '1'])];
        yield 'request body' => [new Request([], [BlogDetailRoute::SKIP_BREADCRUMB => true])];
        yield 'request attribute' => [new Request([], [], [BlogDetailRoute::SKIP_BREADCRUMB => true])];
    }

    public function testLoadSeoBreadcrumbRequestAttributeOverrulesTheClientParameter(): void
    {
        $seoCategory = new CategoryEntity();
        $seoCategory->setId(Uuid::randomHex());

        $breadcrumb = new BreadcrumbCollection([new Breadcrumb('Home', $seoCategory->getId())]);

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->method('getBlogSeoCategory')->willReturn($seoCategory);
        $breadcrumbBuilder->expects($this->once())
            ->method('getCategoryBreadcrumbUrls')
            ->willReturn($breadcrumb);

        // a visitor must not be able to suppress the breadcrumb of a rendered frontend page
        $request = new Request(
            [BlogDetailRoute::SKIP_BREADCRUMB => '1'],
            [],
            [BlogDetailRoute::SKIP_BREADCRUMB => false]
        );

        $result = $this->buildRoute($breadcrumbBuilder)->load('1', $request, Generator::generateChannelContext(), new Criteria());

        static::assertSame($breadcrumb, $result->getBlog()->getSeoBreadcrumb());
    }

    public function testLoadSeoBreadcrumbIgnoresAMalformedSkipParameter(): void
    {
        $seoCategory = new CategoryEntity();
        $seoCategory->setId(Uuid::randomHex());

        $breadcrumb = new BreadcrumbCollection([new Breadcrumb('Home', $seoCategory->getId())]);

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->method('getBlogSeoCategory')->willReturn($seoCategory);
        $breadcrumbBuilder->expects($this->once())
            ->method('getCategoryBreadcrumbUrls')
            ->willReturn($breadcrumb);

        $request = new Request([BlogDetailRoute::SKIP_BREADCRUMB => 'not-a-bool']);

        $result = $this->buildRoute($breadcrumbBuilder)->load('1', $request, Generator::generateChannelContext(), new Criteria());

        static::assertSame($breadcrumb, $result->getBlog()->getSeoBreadcrumb());
    }

    public function testLoadSeoBreadcrumbWithoutSeoCategory(): void
    {
        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->method('getBlogSeoCategory')->willReturn(null);
        $breadcrumbBuilder->expects($this->never())->method('getCategoryBreadcrumbUrls');

        $result = $this->buildRoute($breadcrumbBuilder)->load('1', new Request(), Generator::generateChannelContext(), new Criteria());

        static::assertNull($result->getBlog()->getSeoBreadcrumb());
    }

    public function testLoadBreadcrumbCategoryByReferrerFromTheQuery(): void
    {
        $referrerCategory = new CategoryEntity();
        $referrerCategory->setId(Uuid::randomHex());

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->once())
            ->method('getBlogCategoryByReferrer')
            ->willReturn($referrerCategory);
        $breadcrumbBuilder->expects($this->never())->method('getBlogSeoCategory');
        $breadcrumbBuilder->method('getCategoryBreadcrumbUrls')->willReturn(new BreadcrumbCollection());

        $request = new Request([BlogDetailRoute::REFERRER_CATEGORY_ID => $referrerCategory->getId()]);

        $result = $this->buildRoute($breadcrumbBuilder)->load('1', $request, Generator::generateChannelContext(), new Criteria());

        static::assertSame($referrerCategory, $result->getBlog()->getSeoCategory());
    }

    public function testLoadBreadcrumbCategoryByReferrerFromTheRequestBody(): void
    {
        $referrerCategory = new CategoryEntity();
        $referrerCategory->setId(Uuid::randomHex());

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->once())
            ->method('getBlogCategoryByReferrer')
            ->willReturn($referrerCategory);
        $breadcrumbBuilder->expects($this->never())->method('getBlogSeoCategory');
        $breadcrumbBuilder->method('getCategoryBreadcrumbUrls')->willReturn(new BreadcrumbCollection());

        $request = new Request([], [BlogDetailRoute::REFERRER_CATEGORY_ID => $referrerCategory->getId()]);

        $result = $this->buildRoute($breadcrumbBuilder)->load('1', $request, Generator::generateChannelContext(), new Criteria());

        static::assertSame($referrerCategory, $result->getBlog()->getSeoCategory());
    }

    public function testLoadBreadcrumbCategoryIgnoresTheClientReferrerWhenAnAttributeIsSet(): void
    {
        $seoCategory = new CategoryEntity();
        $seoCategory->setId(Uuid::randomHex());

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->never())->method('getBlogCategoryByReferrer');
        $breadcrumbBuilder->expects($this->once())
            ->method('getBlogSeoCategory')
            ->willReturn($seoCategory);
        $breadcrumbBuilder->method('getCategoryBreadcrumbUrls')->willReturn(new BreadcrumbCollection());

        // the frontend disables referrer breadcrumbs through the attribute, a stale link must not override it
        $request = new Request([BlogDetailRoute::REFERRER_CATEGORY_ID => Uuid::randomHex()]);
        $request->attributes->set(BlogDetailRoute::REFERRER_CATEGORY_ID, null);

        $result = $this->buildRoute($breadcrumbBuilder)->load('1', $request, Generator::generateChannelContext(), new Criteria());

        static::assertSame($seoCategory, $result->getBlog()->getSeoCategory());
    }

    public function testLoadBreadcrumbTagsTheWholeCategoryPath(): void
    {
        $seoCategory = new CategoryEntity();
        $seoCategory->setId(Uuid::randomHex());
        $homeId = Uuid::randomHex();
        $breadcrumb = new BreadcrumbCollection([
            new Breadcrumb('Home', $homeId),
            new Breadcrumb('Articles', $seoCategory->getId()),
        ]);

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->method('getBlogSeoCategory')->willReturn($seoCategory);
        $breadcrumbBuilder->method('getCategoryBreadcrumbUrls')->willReturn($breadcrumb);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->exactly(2))
            ->method('addTag')
            ->willReturnCallback(function (string ...$tags) use ($homeId, $seoCategory): void {
                static::assertContains(
                    $tags,
                    [
                        [BlogDetailRoute::buildName('1')],
                        [CategoryRoute::buildName($homeId), CategoryRoute::buildName($seoCategory->getId())],
                    ]
                );
            });

        $this->buildRoute($breadcrumbBuilder, $cacheTagCollector)->load('1', new Request(), Generator::generateChannelContext(), new Criteria());
    }

    private function buildRoute(
        CategoryBreadcrumbBuilder $breadcrumbBuilder,
        ?CacheTagCollector $cacheTagCollector = null,
    ): BlogDetailRoute {
        $blog = new ChannelBlogEntity();
        $blog->setId('1');
        $blog->setUniqueIdentifier('blog');

        $context = Generator::generateChannelContext();
        $blogRepository = static::createStub(ChannelRepository::class);
        $blogRepository->method('search')->willReturn(
            new EntitySearchResult(1, new ChannelBlogCollection([$blog]), null, new Criteria(), $context->getContext())
        );

        return new BlogDetailRoute(
            $blogRepository,
            $breadcrumbBuilder,
            $cacheTagCollector ?? static::createStub(CacheTagCollector::class),
        );
    }
}
