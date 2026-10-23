<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\QueryDataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Page\Newsletter\Subscribe\NewsletterSubscribePageLoader;
use Contena\Frontend\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class NewsletterController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly NewsletterSubscribePageLoader $newsletterConfirmRegisterPageLoader,
        private readonly AbstractNewsletterConfirmRoute $newsletterConfirmRoute,
        private readonly NewsletterAccountPageletLoader $newsletterAccountPageletLoader
    ) {
    }

    #[Route(
        path: '/newsletter-subscribe',
        name: 'frontend.newsletter.subscribe',
        methods: [Request::METHOD_GET]
    )]
    public function subscribeMail(ChannelContext $context, Request $request, QueryDataBag $queryDataBag): Response
    {
        if ($this->isHeadRequest()) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        try {
            $this->newsletterConfirmRoute->confirmWithResponse($queryDataBag->toRequestDataBag(), $context);
        } catch (NewsletterException) {
            $this->addFlash(self::DANGER, $this->trans('newsletter.subscriptionConfirmationFailed'));

            return $this->forwardToRoute('frontend.home.page');
        } catch (\Throwable $throwable) {
            $this->addFlash(self::DANGER, $this->trans('newsletter.subscriptionConfirmationFailed'));

            throw $throwable;
        }

        if (RequestParamHelper::get($request, 'redirectTo') || RequestParamHelper::get($request, 'forwardTo')) {
            $this->addFlash(self::SUCCESS, $this->trans('newsletter.subscriptionCompleted'));

            return $this->createActionResponse($request);
        }

        $page = $this->newsletterConfirmRegisterPageLoader->load($request, $context);

        return $this->renderFrontend('@Frontend/frontend/page/newsletter/confirm-subscribe.html.twig', ['page' => $page]);
    }

    #[Route(
        path: '/widgets/account/newsletter',
        name: 'frontend.account.newsletter',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function subscribeUser(Request $request, RequestDataBag $dataBag, ChannelContext $context, UserEntity $user): Response
    {
        $pagelet = $this->newsletterAccountPageletLoader->action($request, $dataBag, $context, $user);

        return $this->renderFrontend('@Frontend/frontend/page/account/newsletter.html.twig', [
            'newsletterAccountPagelet' => $pagelet,
        ]);
    }
}
