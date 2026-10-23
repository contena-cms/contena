<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\System\User\Channel\AbstractLogoutRoute;
use Contena\Core\Content\Flow\FlowException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\Profiling\Profiler;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\AffiliateTracking\AffiliateTrackingListener;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Pagelet\Footer\FooterPageletLoaderInterface;
use Contena\Frontend\Pagelet\Header\HeaderPageletLoaderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class CheckoutController extends FrontendController
{
    private const REDIRECTED_FROM_SAME_ROUTE = 'redirected';

    /**
     * @internal
     */
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutCartPageLoader $cartPageLoader,
        private readonly CheckoutConfirmPageLoader $confirmPageLoader,
        private readonly CheckoutFinishPageLoader $finishPageLoader,
        private readonly OrderService $orderService,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly OffcanvasCartPageLoader $offcanvasCartPageLoader,
        private readonly AbstractLogoutRoute $logoutRoute,
        private readonly AbstractCartLoadRoute $cartLoadRoute,
        private readonly HeaderPageletLoaderInterface $headerPageletLoader,
        private readonly FooterPageletLoaderInterface $footerPageletLoader,
    ) {
    }

    #[Route(
        path: '/checkout/cart',
        name: 'frontend.checkout.cart.page',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET]
    )]
    public function cartPage(Request $request, ChannelContext $context): Response
    {
        $page = $this->cartPageLoader->load($request, $context);
        $cart = $page->getCart();
        $cartErrors = $cart->getErrors();

        $this->hook(new CheckoutCartPageLoadedHook($page, $context));

        $this->addCartErrors($cart);

        if (!$request->query->getBoolean(self::REDIRECTED_FROM_SAME_ROUTE) && $this->routeNeedsReload($cartErrors)) {
            $cartErrors->clear();

            // To prevent redirect loops add the identifier that the request already got redirected from the same origin
            return $this->redirectToRoute(
                'frontend.checkout.cart.page',
                [...$request->query->all(), ...[self::REDIRECTED_FROM_SAME_ROUTE => true]],
            );
        }
        $cartErrors->clear();

        return $this->renderFrontend('@Frontend/frontend/page/checkout/cart/index.html.twig', ['page' => $page]);
    }

    #[Route(
        path: '/checkout/cart.json',
        name: 'frontend.checkout.cart.json',
        options: ['seo' => false],
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_GET]
    )]
    public function cartJson(Request $request, ChannelContext $context): Response
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        // @phpstan-ignore arguments.count (cart is hidden on AbstractCartLoadRoute::load() via NewOptionalParameter to avoid a BC break for decorators; CartLoadRoute reads this 3rd argument for real)
        return $this->cartLoadRoute->load($request, $context, $cart);
    }

    #[Route(
        path: '/checkout/confirm',
        name: 'frontend.checkout.confirm.page',
        options: ['seo' => false],
        defaults: ['XmlHttpRequest' => true, PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET]
    )]
    public function confirmPage(Request $request, ChannelContext $context): Response
    {
        if (!$context->getUser()) {
            return $this->redirectToRoute('frontend.checkout.register.page');
        }

        if ($this->cartService->getCart($context->getToken(), $context)->getLineItems()->count() === 0) {
            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        $page = $this->confirmPageLoader->load($request, $context);
        $cart = $page->getCart();
        $cartErrors = $cart->getErrors();

        $this->hook(new CheckoutConfirmPageLoadedHook($page, $context));

        $this->addCartErrors($cart);

        if (!$request->query->getBoolean(self::REDIRECTED_FROM_SAME_ROUTE) && $this->routeNeedsReload($cartErrors)) {
            $cartErrors->clear();

            // To prevent redirect loops add the identifier that the request already got redirected from the same origin
            return $this->redirectToRoute(
                'frontend.checkout.confirm.page',
                [...$request->query->all(), ...[self::REDIRECTED_FROM_SAME_ROUTE => true]],
            );
        }

        $header = $this->headerPageletLoader->load($request, $context);
        $footer = $this->footerPageletLoader->load($request, $context);

        return $this->renderFrontend('@Frontend/frontend/page/checkout/confirm/index.html.twig', [
            'page' => $page,
            'header' => $header,
            'footer' => $footer,
        ]);
    }

    #[Route(
        path: '/checkout/finish',
        name: 'frontend.checkout.finish.page',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET]
    )]
    public function finishPage(Request $request, ChannelContext $context, RequestDataBag $dataBag): Response
    {
        if ($context->getUser() === null) {
            return $this->redirectToRoute('frontend.checkout.register.page');
        }

        try {
            $page = $this->finishPageLoader->load($request, $context);
        } catch (OrderException $exception) {
            $this->addFlash(self::DANGER, $this->trans('error.' . $exception->getErrorCode(), $exception->getParameters()));

            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        $this->hook(new CheckoutFinishPageLoadedHook($page, $context));

        if ($page->isPaymentFailed() === true) {
            return $this->redirectToRoute(
                'frontend.account.edit-order.page',
                [
                    'orderId' => $request->query->get('orderId'),
                    'error-code' => 'CHECKOUT__UNKNOWN_ERROR',
                ]
            );
        }

        if ($page->isLogoutUser()) {
            $this->logoutRoute->logout($context, $dataBag);
        }

        $header = $this->headerPageletLoader->load($request, $context);
        $footer = $this->footerPageletLoader->load($request, $context);

        return $this->renderFrontend('@Frontend/frontend/page/checkout/finish/index.html.twig', [
            'page' => $page,
            'header' => $header,
            'footer' => $footer,
        ]);
    }

    #[Route(
        path: '/checkout/order',
        name: 'frontend.checkout.finish.order',
        options: ['seo' => false],
        methods: [Request::METHOD_POST]
    )]
    public function order(RequestDataBag $data, ChannelContext $context, Request $request): Response
    {
        if (!$context->getUser()) {
            return $this->redirectToRoute('frontend.checkout.register.page');
        }

        try {
            $this->addAffiliateTracking($data, $request->getSession());

            $orderId = Profiler::trace('checkout-order', fn () => $this->orderService->createOrder($data, $context));
        } catch (ConstraintViolationException $formViolations) {
            return $this->forwardToRoute('frontend.checkout.confirm.page', ['formViolations' => $formViolations]);
        } catch (InvalidCartException|Error|EmptyCartException) {
            $this->addCartErrors(
                $this->cartService->getCart($context->getToken(), $context)
            );

            return $this->forwardToRoute('frontend.checkout.confirm.page');
        } catch (PaymentException|CartException $e) {
            if ($e->getErrorCode() === CartException::CART_PAYMENT_INVALID_ORDER_STORED_CODE && $e->getParameter('orderId')) {
                return $this->forwardToRoute('frontend.checkout.finish.page', ['orderId' => $e->getParameter('orderId'), 'changedPayment' => false, 'paymentFailed' => true]);
            }
            $message = $this->trans('error.' . $e->getErrorCode(), $e->getParameters());
            $this->addFlash('danger', $message);

            return $this->forwardToRoute('frontend.checkout.confirm.page');
        }

        try {
            $finishUrl = $this->generateUrl('frontend.checkout.finish.page', ['orderId' => $orderId]);
            $errorUrl = $this->generateUrl('frontend.account.edit-order.page', ['orderId' => $orderId]);

            $response = Profiler::trace('handle-payment', fn (): ?RedirectResponse => $this->paymentProcessor->pay($orderId, $request, $context, $finishUrl, $errorUrl));

            return $response ?? new RedirectResponse($finishUrl);
        } catch (PaymentException|IllegalTransitionException|FlowException) {
            return $this->forwardToRoute('frontend.checkout.finish.page', ['orderId' => $orderId, 'changedPayment' => false, 'paymentFailed' => true]);
        }
    }

    #[Route(
        path: '/widgets/checkout/info',
        name: 'frontend.checkout.info',
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_GET]
    )]
    public function info(Request $request, ChannelContext $context): Response
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);
        if ($cart->getLineItems()->count() <= 0) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $page = $this->offcanvasCartPageLoader->load($request, $context);

        $this->hook(new CheckoutInfoWidgetLoadedHook($page, $context));

        $response = $this->renderFrontend('@Frontend/frontend/layout/header/actions/cart-widget.html.twig', ['page' => $page]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    #[Route(
        path: '/checkout/offcanvas',
        name: 'frontend.cart.offcanvas',
        options: ['seo' => false],
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_GET]
    )]
    public function offcanvas(Request $request, ChannelContext $context): Response
    {
        $page = $this->offcanvasCartPageLoader->load($request, $context);

        $this->hook(new CheckoutOffcanvasWidgetLoadedHook($page, $context));

        $cart = $page->getCart();
        $this->addCartErrors($cart);
        $cartErrors = $cart->getErrors();

        if (!$request->query->getBoolean(self::REDIRECTED_FROM_SAME_ROUTE) && $this->routeNeedsReload($cartErrors)) {
            $cartErrors->clear();

            // To prevent redirect loops add the identifier that the request already got redirected from the same origin
            return $this->redirectToRoute(
                'frontend.cart.offcanvas',
                [...$request->query->all(), ...[self::REDIRECTED_FROM_SAME_ROUTE => true]],
            );
        }

        $cartErrors->clear();

        return $this->renderFrontend('@Frontend/frontend/component/checkout/offcanvas-cart.html.twig', ['page' => $page]);
    }

    private function addAffiliateTracking(RequestDataBag $dataBag, SessionInterface $session): void
    {
        $affiliateCode = $session->get(AffiliateTrackingListener::AFFILIATE_CODE_KEY);
        $campaignCode = $session->get(AffiliateTrackingListener::CAMPAIGN_CODE_KEY);
        if ($affiliateCode) {
            $dataBag->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, $affiliateCode);
        }

        if ($campaignCode) {
            $dataBag->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, $campaignCode);
        }
    }

    private function routeNeedsReload(ErrorCollection $cartErrors): bool
    {
        foreach ($cartErrors as $error) {
            if ($error instanceof ShippingMethodChangedError || $error instanceof PaymentMethodChangedError) {
                return true;
            }
        }

        return false;
    }
}
