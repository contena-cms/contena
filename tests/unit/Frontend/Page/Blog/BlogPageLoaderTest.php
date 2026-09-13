<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Blog;

use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Detail\AbstractBlogDetailRoute;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRouteResponse;
use Contena\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\Blog\BlogPage;
use Contena\Frontend\Page\Blog\BlogPageLoader;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Contena\Frontend\Page\MetaInformation;
use Contena\Frontend\Page\Page;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogPageLoader::class)]
class BlogPageLoaderTest extends TestCase
{
    public function testItRequiresBlogId(): void
    {
        $blogDetailRoute = $this->createMock(AbstractBlogDetailRoute::class);
        $blogDetailRoute->expects($this->never())->method('load');

        $loader = new BlogPageLoader(
            static::createStub(GenericPageLoaderInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $blogDetailRoute,
            static::createStub(CategoryBreadcrumbBuilder::class),
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
            static::createStub(SystemConfigService::class),
        );

        $this->expectExceptionObject(RoutingException::missingRequestParameter('blogId', '/blogId'));
        $loader->load(new Request(), Generator::generateChannelContext());
    }

    public function testItLoadsBlogAndMetaInformation(): void
    {
        $blogId = Uuid::randomHex();
        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);
        $blog->setTranslated([
            'name' => 'Blog title',
            'metaTitle' => 'Blog meta title',
            'metaDescription' => 'Blog meta description',
            'keywords' => 'blog,content',
        ]);

        $genericPage = new Page();
        $genericPage->setMetaInformation(new MetaInformation());

        $genericPageLoader = static::createStub(GenericPageLoaderInterface::class);
        $genericPageLoader->method('load')->willReturn($genericPage);

        $blogDetailRoute = static::createStub(AbstractBlogDetailRoute::class);
        $blogDetailRoute->method('load')->willReturn(new BlogDetailRouteResponse($blog));

        $seoUrlReplacer = static::createStub(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlReplacer->method('generate')->willReturn('/blog/' . $blogId);

        $loader = new BlogPageLoader(
            $genericPageLoader,
            static::createStub(EventDispatcherInterface::class),
            $blogDetailRoute,
            static::createStub(CategoryBreadcrumbBuilder::class),
            $seoUrlReplacer,
            static::createStub(SystemConfigService::class),
        );

        $request = new Request([], [], ['blogId' => $blogId]);
        $page = $loader->load($request, Generator::generateChannelContext());

        static::assertSame($blog, $page->getBlog());
        static::assertNull($page->getNavigationId());

        $metaInformation = $page->getMetaInformation();
        static::assertInstanceOf(MetaInformation::class, $metaInformation);
        static::assertSame('Blog meta title', $metaInformation->getMetaTitle());
        static::assertSame('Blog meta description', $metaInformation->getMetaDescription());
        static::assertSame('blog,content', $metaInformation->getMetaKeywords());
        static::assertSame('/blog/' . $blogId, $metaInformation->getCanonical());
    }

    public function testItTakesTheBreadcrumbFromTheRoute(): void
    {
        $blogId = Uuid::randomHex();
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $breadcrumb = new BreadcrumbCollection([new Breadcrumb('Articles', $category->getId())]);

        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);
        $blog->setSeoCategory($category);
        $blog->setSeoBreadcrumb($breadcrumb);

        // the route already resolved it, and that is also what registers the path cache tags on this page
        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->never())->method('getCategoryBreadcrumbUrls');

        $page = $this->loadBlog($blog, $breadcrumbBuilder);

        static::assertSame($breadcrumb, $page->getBreadcrumb());
    }

    public function testItFallsBackToTheBuilderForADecoratedRoute(): void
    {
        $blogId = Uuid::randomHex();
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $breadcrumb = new BreadcrumbCollection([new Breadcrumb('Articles', $category->getId())]);

        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);
        $blog->setSeoCategory($category);

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->once())
            ->method('getCategoryBreadcrumbUrls')
            ->willReturn($breadcrumb);

        $page = $this->loadBlog($blog, $breadcrumbBuilder);

        static::assertSame($breadcrumb, $page->getBreadcrumb());
    }

    public function testItDisablesClientReferrerBreadcrumbWhenTheChannelSettingIsOff(): void
    {
        $blogId = Uuid::randomHex();
        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);

        $request = new Request(
            [BlogDetailRoute::REFERRER_CATEGORY_ID => Uuid::randomHex()],
            [],
            ['blogId' => $blogId]
        );

        $blogDetailRoute = $this->createMock(AbstractBlogDetailRoute::class);
        $blogDetailRoute->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (string $id, Request $routeRequest) use ($blog, $blogId): BlogDetailRouteResponse {
                static::assertSame($blogId, $id);
                static::assertTrue($routeRequest->attributes->has(BlogDetailRoute::REFERRER_CATEGORY_ID));
                static::assertNull($routeRequest->attributes->get(BlogDetailRoute::REFERRER_CATEGORY_ID));

                return new BlogDetailRouteResponse($blog);
            });

        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getBool')->willReturn(false);

        $loader = new BlogPageLoader(
            $this->genericPageLoader(),
            static::createStub(EventDispatcherInterface::class),
            $blogDetailRoute,
            static::createStub(CategoryBreadcrumbBuilder::class),
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
            $systemConfigService,
        );

        $loader->load($request, Generator::generateChannelContext());
    }

    public function testItLetsTheReferrerCategoryThroughWhenTheChannelSettingIsEnabled(): void
    {
        $blogId = Uuid::randomHex();
        $referrerCategoryId = Uuid::randomHex();
        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);

        $request = new Request(
            [BlogDetailRoute::REFERRER_CATEGORY_ID => $referrerCategoryId],
            [],
            ['blogId' => $blogId]
        );

        $blogDetailRoute = static::createStub(AbstractBlogDetailRoute::class);
        $blogDetailRoute->method('load')->willReturn(new BlogDetailRouteResponse($blog));

        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getBool')->willReturn(true);

        $loader = new BlogPageLoader(
            $this->genericPageLoader(),
            static::createStub(EventDispatcherInterface::class),
            $blogDetailRoute,
            static::createStub(CategoryBreadcrumbBuilder::class),
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
            $systemConfigService,
        );

        $loader->load($request, Generator::generateChannelContext());

        // no attribute is set, so the route reads the parameter the client sent
        static::assertFalse($request->attributes->has(BlogDetailRoute::REFERRER_CATEGORY_ID));
        static::assertSame($referrerCategoryId, $request->query->get(BlogDetailRoute::REFERRER_CATEGORY_ID));
    }

    private function loadBlog(ChannelBlogEntity $blog, CategoryBreadcrumbBuilder $breadcrumbBuilder): BlogPage
    {
        $blogDetailRoute = static::createStub(AbstractBlogDetailRoute::class);
        $blogDetailRoute->method('load')->willReturn(new BlogDetailRouteResponse($blog));

        $loader = new BlogPageLoader(
            $this->genericPageLoader(),
            static::createStub(EventDispatcherInterface::class),
            $blogDetailRoute,
            $breadcrumbBuilder,
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
            static::createStub(SystemConfigService::class),
        );

        return $loader->load(new Request([], [], ['blogId' => $blog->getId()]), Generator::generateChannelContext());
    }

    private function genericPageLoader(): GenericPageLoaderInterface
    {
        $genericPageLoader = static::createStub(GenericPageLoaderInterface::class);
        $genericPageLoader->method('load')->willReturn(new Page());

        return $genericPageLoader;
    }
}
