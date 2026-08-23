<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRoute;
use Contena\Core\Framework\ContentSystem\Channel\ContentRouteResponse;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Frontend\Controller\BlogController;
use Contena\Frontend\Page\Blog\BlogPage;
use Contena\Frontend\Page\Blog\BlogPageLoader;
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
        $contentPage = new ContentPage('layout-id', [], 'blog-layout', null);

        $contentRoute = $this->createMock(AbstractContentRoute::class);
        $contentRoute->expects($this->once())
            ->method('load')
            ->with('/blog/' . $blogId, $request, $context)
            ->willReturn(new ContentRouteResponse($contentPage));

        $controller = new BlogControllerTestClass($pageLoader, $contentRoute);
        $controller->detail($request, $context);

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
