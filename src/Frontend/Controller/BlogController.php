<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Content\Blog\Channel\Comment\AbstractBlogCommentSaveRoute;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Page\Blog\BlogPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class BlogController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BlogPageLoader $blogPageLoader,
        private readonly AbstractBlogCommentSaveRoute $blogCommentSaveRoute,
    ) {
    }

    #[Route(path: '/blog/{blogId}', name: BlogPageSeoUrlRoute::ROUTE_NAME, options: ['seo' => true], defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true], methods: [Request::METHOD_GET])]
    public function detail(Request $request, ChannelContext $context): Response
    {
        $page = $this->blogPageLoader->load($request, $context);
        $contentPage = $this->loadContentPage('/blog/' . $page->getBlog()->getId(), $request, $context);

        return $this->renderFrontend('@Frontend/frontend/page/blog/detail.html.twig', [
            'page' => $page,
            'contentPage' => $contentPage,
            'isNewContentStructure' => true,
        ]);
    }

    #[Route(
        path: '/blog/{blogId}/comment',
        name: 'frontend.blog.comment.save',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
        ],
        methods: [Request::METHOD_POST],
    )]
    public function saveComment(string $blogId, RequestDataBag $data, ChannelContext $context): NoContentResponse
    {
        return $this->blogCommentSaveRoute->save($blogId, $data, $context);
    }
}
