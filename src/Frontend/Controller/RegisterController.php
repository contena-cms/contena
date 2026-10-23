<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Channel\AbstractRegisterConfirmRoute;
use Contena\Core\System\User\Channel\AbstractRegisterRoute;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\QueryDataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Framework\AffiliateTracking\AffiliateTrackingListener;
use Contena\Frontend\Framework\Guard\DoubleSubmitGuard;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Page\Account\UserGroupRegistration\AbstractUserGroupRegistrationPageLoader;
use Contena\Frontend\Page\Account\UserGroupRegistration\UserGroupRegistrationPageLoadedHook;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\Account\Register\AccountRegisterPageLoadedHook;
use Contena\Frontend\Pagelet\Footer\FooterPageletLoaderInterface;
use Contena\Frontend\Pagelet\Header\HeaderPageletLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class RegisterController extends FrontendController
{
    /**
     * Scopes the double submit marker and lock of the registration submission.
     */
    public const DOUBLE_SUBMIT_SCOPE = 'frontend-registration';

    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     * @param EntityRepository<ChannelDomainCollection> $domainRepository
     */
    public function __construct(
        private readonly AccountLoginPageLoader $loginPageLoader,
        private readonly AbstractRegisterRoute $registerRoute,
        private readonly AbstractRegisterConfirmRoute $registerConfirmRoute,
        private readonly CartService $cartService,
        private readonly CheckoutRegisterPageLoader $registerPageLoader,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $userRepository,
        private readonly AbstractUserGroupRegistrationPageLoader $userGroupRegistrationPageLoader,
        private readonly EntityRepository $domainRepository,
        private readonly HeaderPageletLoaderInterface $headerPageletLoader,
        private readonly FooterPageletLoaderInterface $footerPageletLoader,
        private readonly DoubleSubmitGuard $doubleSubmitGuard,
    ) {
    }

    #[Route(
        path: '/account/register',
        name: 'frontend.account.register.page',
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET]
    )]
    public function accountRegisterPage(Request $request, RequestDataBag $data, ChannelContext $context): Response
    {
        if ($context->getUser() && $context->getUser()->getGuest()) {
            return $this->redirectToRoute('frontend.account.logout.page');
        }

        if ($context->getUser()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        // Add '_httpCache' => true, to defaults in Route and remove _noStore
        if (Feature::isActive('PERFORMANCE_TWEAKS')) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
            $request->attributes->remove(PlatformRequest::ATTRIBUTE_NO_STORE);
        }

        $redirect = $request->query->get('redirectTo', 'frontend.account.home.page');
        $errorRoute = $request->attributes->get('_route');

        $page = $this->loginPageLoader->load($request, $context);

        $this->hook(new AccountRegisterPageLoadedHook($page, $context));

        return $this->renderFrontend('@Frontend/frontend/page/account/register/index.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $request->query->all()['redirectParameters'] ?? '[]',
            'errorRoute' => $errorRoute,
            'page' => $page,
            'data' => $data,
        ]);
    }

    #[Route(
        path: '/user-group-registration/{userGroupId}',
        name: 'frontend.account.user-group-registration.page',
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET]
    )]
    public function userGroupRegistration(string $userGroupId, Request $request, RequestDataBag $data, ChannelContext $context): Response
    {
        if ($context->getUser() && $context->getUser()->getGuest()) {
            return $this->redirectToRoute('frontend.account.logout.page');
        }

        if ($context->getUser()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        // Add '_httpCache' => true, to defaults in Route and remove _noStore
        if (Feature::isActive('PERFORMANCE_TWEAKS')) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
            $request->attributes->remove(PlatformRequest::ATTRIBUTE_NO_STORE);
        }

        $redirect = $request->query->get('redirectTo', 'frontend.account.home.page');

        $page = $this->userGroupRegistrationPageLoader->load($request, $context);

        if ($page->getGroup()->getTranslation('registrationOnlyCompanyRegistration')) {
            $data->set('accountType', UserEntity::ACCOUNT_TYPE_BUSINESS);
        }

        $this->hook(new UserGroupRegistrationPageLoadedHook($page, $context));

        return $this->renderFrontend('@Frontend/frontend/page/account/user-group-register/index.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $request->query->all()['redirectParameters'] ?? '[]',
            'errorRoute' => $request->attributes->get('_route'),
            'errorParameters' => json_encode(['userGroupId' => $userGroupId], \JSON_THROW_ON_ERROR),
            'page' => $page,
            'data' => $data,
        ]);
    }

    #[Route(
        path: '/checkout/register',
        name: 'frontend.checkout.register.page',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET]
    )]
    public function checkoutRegisterPage(Request $request, RequestDataBag $data, ChannelContext $context): Response
    {
        $redirect = $request->query->get('redirectTo', 'frontend.checkout.confirm.page');
        \assert(\is_string($redirect));
        $errorRoute = $request->attributes->get('_route');

        if ($context->getUser()) {
            return $this->redirectToRoute($redirect);
        }

        if ($this->cartService->getCart($context->getToken(), $context)->getLineItems()->count() === 0) {
            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        $page = $this->registerPageLoader->load($request, $context);

        $this->hook(new CheckoutRegisterPageLoadedHook($page, $context));

        $header = $this->headerPageletLoader->load($request, $context);
        $footer = $this->footerPageletLoader->load($request, $context);

        return $this->renderFrontend(
            '@Frontend/frontend/page/checkout/address/index.html.twig',
            [
                'redirectTo' => $redirect,
                'errorRoute' => $errorRoute,
                'loginError' => $request->attributes->getBoolean('loginError'),
                'errorSnippet' => $request->attributes->get('errorSnippet'),
                'waitTime' => $request->attributes->get('waitTime'),
                'page' => $page,
                'header' => $header,
                'footer' => $footer,
                'data' => $data,
            ]
        );
    }

    #[Route(
        path: '/account/register',
        name: 'frontend.account.register.save',
        defaults: [PlatformRequest::ATTRIBUTE_CAPTCHA => true],
        methods: [Request::METHOD_POST]
    )]
    public function register(Request $request, RequestDataBag $data, ChannelContext $context): Response
    {
        if ($context->getUser()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        try {
            if (!$data->has('differentShippingAddress')) {
                $data->remove('shippingAddress');
            }

            $data->set('frontendUrl', $this->getConfirmUrl($context, $request));
            $data = $this->prepareAffiliateTracking($data, $request->getSession());
            $data->set('guest', !$data->getBoolean('createUserAccount'));

            $additionalValidationDefinitions = $this->getAdditionalRegisterValidationDefinitions($data, $context);

            $this->doubleSubmitGuard->guard(self::DOUBLE_SUBMIT_SCOPE, $context, function () use ($data, $context, $additionalValidationDefinitions): void {
                $this->registerRoute->register(
                    $data->toRequestDataBag(),
                    $context,
                    false,
                    $additionalValidationDefinitions
                );
            });
        } catch (ConstraintViolationException $formViolations) {
            if (!$request->request->has('errorRoute')) {
                throw RoutingException::missingRequestParameter('errorRoute');
            }

            if ($request->request->getString('errorRoute') === '') {
                $request->request->set('errorRoute', 'frontend.account.register.page');
            }

            $params = $this->decodeParam($request, 'errorParameters');

            // this is to show the correct form because we have different use-cases (account/register||checkout/register)
            return $this->forwardToRoute((string) RequestParamHelper::get($request, 'errorRoute'), ['formViolations' => $formViolations], $params);
        }

        if ($this->isDoubleOptIn($data, $context)) {
            return $this->redirectToRoute('frontend.account.register.page');
        }

        return $this->createActionResponse($request);
    }

    #[Route(
        path: '/registration/confirm',
        name: 'frontend.account.register.mail',
        methods: [Request::METHOD_GET]
    )]
    public function confirmRegistration(ChannelContext $context, QueryDataBag $queryDataBag): Response
    {
        if ($this->isHeadRequest()) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        try {
            $userId = $this->registerConfirmRoute
                ->confirm($queryDataBag->toRequestDataBag(), $context)
                ->getUser()
                ->getId();
        } catch (UserNotFoundByHashException|UserAlreadyConfirmedException|ConstraintViolationException) {
            $this->addFlash(self::DANGER, $this->trans('account.confirmationIsAlreadyDone'));

            return $this->redirectToRoute('frontend.account.register.page');
        }

        $user = $this->userRepository->search(new Criteria([$userId]), $context->getContext())->getEntities()->first();
        \assert($user !== null);

        if ($user->getGuest()) {
            $this->addFlash(self::SUCCESS, $this->trans('account.doubleOptInMailConfirmationSuccessfully'));

            return $this->redirectToRoute('frontend.checkout.confirm.page');
        }

        $this->addFlash(self::SUCCESS, $this->trans('account.doubleOptInRegistrationSuccessfully'));

        if ($redirectTo = $queryDataBag->get('redirectTo')) {
            $parameters = $queryDataBag->all();
            unset($parameters['em'], $parameters['hash'], $parameters['redirectTo']);

            return $this->redirectToRoute($redirectTo, $parameters);
        }

        return $this->redirectToRoute('frontend.account.home.page');
    }

    private function isDoubleOptIn(DataBag $data, ChannelContext $context): bool
    {
        $createUserAccount = $data->getBoolean('createUserAccount');

        $configKey = $createUserAccount
            ? 'core.loginRegistration.doubleOptInRegistration'
            : 'core.loginRegistration.doubleOptInGuestOrder';

        $doubleOptInRequired = $this->systemConfigService
            ->get($configKey, $context->getChannelId());

        if (!$doubleOptInRequired) {
            return false;
        }

        if ($createUserAccount) {
            $this->addFlash(self::SUCCESS, $this->trans('account.optInRegistrationAlert'));

            return true;
        }

        $this->addFlash(self::SUCCESS, $this->trans('account.optInGuestAlert'));

        return true;
    }

    private function getAdditionalRegisterValidationDefinitions(DataBag $data, ChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('frontend.confirmation');

        if ($this->systemConfigService->get('core.loginRegistration.requireEmailConfirmation', $context->getChannelId())) {
            $definition->add('emailConfirmation', new NotBlank(), new EqualTo(value: $data->get('email')));
        }

        if ($data->getBoolean('guest')) {
            return $definition;
        }

        if ($this->systemConfigService->get('core.loginRegistration.requirePasswordConfirmation', $context->getChannelId())) {
            $definition->add('passwordConfirmation', new NotBlank(), new EqualTo(value: $data->get('password')));
        }

        return $definition;
    }

    private function prepareAffiliateTracking(RequestDataBag $data, SessionInterface $session): RequestDataBag
    {
        $affiliateCode = $session->get(AffiliateTrackingListener::AFFILIATE_CODE_KEY);
        $campaignCode = $session->get(AffiliateTrackingListener::CAMPAIGN_CODE_KEY);

        if ($affiliateCode !== null) {
            $data->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, $affiliateCode);
        }

        if ($campaignCode !== null) {
            $data->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, $campaignCode);
        }

        return $data;
    }

    private function getConfirmUrl(ChannelContext $context, Request $request): string
    {
        $domainUrl = $this->systemConfigService->getString('core.loginRegistration.doubleOptInDomain', $context->getChannelId());
        if ($domainUrl) {
            return $domainUrl;
        }

        $domainUrl = $request->attributes->get(RequestTransformer::FRONTEND_URL);
        if ($domainUrl) {
            return $domainUrl;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('channelId', $context->getChannelId()))
            ->setLimit(1);

        $domain = $this->domainRepository->search($criteria, $context->getContext())->getEntities()->first();
        if (!$domain) {
            throw FrontendException::domainNotFound($context->getChannel());
        }

        return $domain->getUrl();
    }
}
