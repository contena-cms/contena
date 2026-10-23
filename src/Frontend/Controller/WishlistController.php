<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Channel\AbstractAddWishlistBlogRoute;
use Contena\Core\System\User\Channel\AbstractLoadWishlistRoute;
use Contena\Core\System\User\Channel\AbstractMergeWishlistBlogRoute;
use Contena\Core\System\User\Channel\AbstractRemoveWishlistBlogRoute;
use Contena\Core\System\User\UserEntity;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Page\Wishlist\GuestWishlistPageLoadedHook;
use Contena\Frontend\Page\Wishlist\GuestWishlistPageLoader;
use Contena\Frontend\Page\Wishlist\WishListPageBlogCriteriaEvent;
use Contena\Frontend\Page\Wishlist\WishlistPageLoadedHook;
use Contena\Frontend\Page\Wishlist\WishlistPageLoader;
use Contena\Frontend\Page\Wishlist\WishlistWidgetLoadedHook;
use Contena\Frontend\Pagelet\Wishlist\GuestWishlistPageletLoadedHook;
use Contena\Frontend\Pagelet\Wishlist\GuestWishlistPageletLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Contena\Core\System\User\Exception\DuplicateWishlistBlogException;
use Contena\Core\System\User\Exception\UserWishlistNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a channel-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class WishlistController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly WishlistPageLoader $wishlistPageLoader,
        private readonly AbstractLoadWishlistRoute $wishlistLoadRoute,
        private readonly AbstractAddWishlistBlogRoute $addWishlistRoute,
        private readonly AbstractRemoveWishlistBlogRoute $removeWishlistBlogRoute,
        private readonly AbstractMergeWishlistBlogRoute $mergeWishlistBlogRoute,
        private readonly GuestWishlistPageLoader $guestPageLoader,
        private readonly GuestWishlistPageletLoader $guestPageletLoader,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[Route(
        path: '/wishlist',
        name: 'frontend.wishlist.page',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET]
    )]
    public function index(Request $request, ChannelContext $context): Response
    {
        $user = $context->getUser();

        if ($user !== null && $user->getGuest() === false) {
            $page = $this->wishlistPageLoader->load($request, $context, $user);
            $this->hook(new WishlistPageLoadedHook($page, $context));
        } else {
            $page = $this->guestPageLoader->load($request, $context);
            $this->hook(new GuestWishlistPageLoadedHook($page, $context));
        }

        return $this->renderFrontend('@Frontend/frontend/page/wishlist/index.html.twig', ['page' => $page]);
    }

    #[Route(
        path: '/wishlist/guest-pagelet',
        name: 'frontend.wishlist.guestPage.pagelet',
        options: ['seo' => false],
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_POST]
    )]
    public function guestPagelet(Request $request, ChannelContext $context): Response
    {
        $user = $context->getUser();

        if ($user !== null && $user->getGuest() === false) {
            throw new NotFoundHttpException();
        }

        $pagelet = $this->guestPageletLoader->load($request, $context);
        $this->hook(new GuestWishlistPageletLoadedHook($pagelet, $context));

        return $this->renderFrontend(
            '@Frontend/frontend/page/wishlist/wishlist-pagelet.html.twig',
            ['page' => $pagelet, 'searchResult' => $pagelet->getSearchResult()->getObject()]
        );
    }

    #[Route(
        path: '/widgets/wishlist',
        name: 'widgets.wishlist.pagelet',
        options: ['seo' => false],
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function ajaxPagination(Request $request, ChannelContext $context, UserEntity $user): Response
    {
        $request->request->set('no-aggregations', true);

        $page = $this->wishlistPageLoader->load($request, $context, $user);
        $this->hook(new WishlistPageLoadedHook($page, $context));

        $response = $this->renderFrontend('@Frontend/frontend/page/wishlist/index.html.twig', ['page' => $page]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    #[Route(
        path: '/wishlist/list',
        name: 'frontend.wishlist.blog.list',
        options: ['seo' => false],
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function ajaxList(Request $request, ChannelContext $context, UserEntity $user): Response
    {
        $criteria = new Criteria();
        $criteria->setTitle('wishlist::list');
        $this->eventDispatcher->dispatch(new WishListPageBlogCriteriaEvent($criteria, $context, $request));

        try {
            $res = $this->wishlistLoadRoute->load($request, $context, $criteria, $user);
        } catch (UserWishlistNotFoundException) {
            return new JsonResponse([]);
        }

        return new JsonResponse($res->getBlogListing()->getEntities()->getIds());
    }

    #[Route(
        path: '/wishlist/blog/delete/{id}',
        name: 'frontend.wishlist.blog.delete',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
        ],
        methods: [Request::METHOD_POST, Request::METHOD_DELETE]
    )]
    public function remove(string $id, Request $request, ChannelContext $context, UserEntity $user): Response
    {
        if (!$id) {
            throw RoutingException::missingRequestParameter('id');
        }

        try {
            $this->removeWishlistBlogRoute->delete($id, $context, $user);

            $this->addFlash(self::SUCCESS, $this->trans('wishlist.itemDeleteSuccess'));
        } catch (\Throwable) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    #[Route(
        path: '/wishlist/add/{blogId}',
        name: 'frontend.wishlist.blog.add',
        options: ['seo' => false],
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function ajaxAdd(string $blogId, ChannelContext $context, UserEntity $user): JsonResponse
    {
        try {
            $this->addWishlistRoute->add($blogId, $context, $user);
            $success = true;
        } catch (\Throwable) {
            $success = false;
        }

        return new JsonResponse([
            'success' => $success,
        ]);
    }

    #[Route(
        path: '/wishlist/remove/{blogId}',
        name: 'frontend.wishlist.blog.remove',
        options: ['seo' => false],
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function ajaxRemove(string $blogId, ChannelContext $context, UserEntity $user): JsonResponse
    {
        try {
            $this->removeWishlistBlogRoute->delete($blogId, $context, $user);
            $success = true;
        } catch (\Throwable) {
            $success = false;
        }

        return new JsonResponse([
            'success' => $success,
        ]);
    }

    #[Route(
        path: '/wishlist/add-after-login/{blogId}',
        name: 'frontend.wishlist.add.after.login',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_GET]
    )]
    public function addAfterLogin(string $blogId, ChannelContext $context, UserEntity $user): Response
    {
        try {
            $this->addWishlistRoute->add($blogId, $context, $user);

            $this->addFlash(self::SUCCESS, $this->trans('wishlist.itemAddedSuccess'));
        } catch (DuplicateWishlistBlogException) {
            $this->addFlash(self::WARNING, $this->trans('wishlist.duplicateItemError'));
        } catch (\Throwable) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        return $this->redirectToRoute('frontend.wishlist.page');
    }

    #[Route(
        path: '/wishlist/merge',
        name: 'frontend.wishlist.blog.merge',
        options: ['seo' => false],
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function ajaxMerge(RequestDataBag $requestDataBag, Request $request, ChannelContext $context, UserEntity $user): Response
    {
        try {
            $this->mergeWishlistBlogRoute->merge($requestDataBag, $context, $user);

            return $this->renderFrontend('@Frontend/frontend/utilities/alert.html.twig', [
                'type' => 'info', 'content' => $this->trans('wishlist.wishlistMergeHint'),
            ]);
        } catch (\Throwable) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    #[Route(
        path: '/wishlist/merge/pagelet',
        name: 'frontend.wishlist.blog.merge.pagelet',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function ajaxPagelet(Request $request, ChannelContext $context, UserEntity $user): Response
    {
        $request->request->set('no-aggregations', true);

        $page = $this->wishlistPageLoader->load($request, $context, $user);
        $this->hook(new WishlistWidgetLoadedHook($page, $context));

        return $this->renderFrontend('@Frontend/frontend/page/wishlist/wishlist-pagelet.html.twig', [
            'page' => $page,
            'searchResult' => $page->getWishlist()->getBlogListing(),
        ]);
    }
}
