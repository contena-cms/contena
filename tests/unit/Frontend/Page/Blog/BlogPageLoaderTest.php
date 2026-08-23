<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Detail\AbstractBlogDetailRoute;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRouteResponse;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\Blog\BlogPageLoader;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Contena\Frontend\Page\MetaInformation;
use Contena\Frontend\Page\Page;
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
}
