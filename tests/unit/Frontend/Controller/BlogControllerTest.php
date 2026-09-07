<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\ContentSystem\LayoutReference;
use Contena\Core\Framework\ContentSystem\Output\RenderResult;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Frontend\Controller\BlogController;
use Contena\Frontend\Page\Blog\BlogPage;
use Contena\Frontend\Page\Blog\BlogPageLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogController::class)]
class BlogControllerTest extends TestCase
{
    public function testDetailRendersAssignedContentLayout(): void
    {
        $blogId = Uuid::randomHex();
        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);

        $page = new BlogPage();
        $page->setBlog($blog);

        $pageLoader = static::createStub(BlogPageLoader::class);
        $pageLoader->method('load')->willReturn($page);

        $request = new Request();
        $context = Generator::generateChannelContext();
        $renderResult = new RenderResult([], LayoutReference::create('layout-id', 'blog-layout', null), null);
        $contentPage = ContentPage::fromRenderResult($renderResult);
        $controller = new BlogControllerTestClass($pageLoader);
        $controller->contentPage = $contentPage;
        $controller->detail($request, $context);

        static::assertSame('/blog/' . $blogId, $controller->loadedContentPath);
        static::assertSame('@Frontend/frontend/page/blog/detail.html.twig', $controller->renderFrontendView);
        static::assertSame($page, $controller->renderFrontendParameters['page']);
        static::assertSame($contentPage, $controller->renderFrontendParameters['contentPage']);
        static::assertTrue($controller->renderFrontendParameters['isNewContentStructure']);
    }
}

/**
 * @internal
 */
class BlogControllerTestClass extends BlogController
{
    use FrontendControllerMockTrait;
}
