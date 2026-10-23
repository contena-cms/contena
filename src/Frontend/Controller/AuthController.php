<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Validation\Constraint\UserEmailUnique;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Page\Account\Login\AccountGuestLoginPageLoadedHook;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoadedHook;
use Contena\Frontend\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoadedHook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\System\User\Exception\InvalidImitateUserTokenException;
use Contena\Core\System\User\Exception\PasswordPoliciesUpdatedException;
use Contena\Core\System\User\Exception\UserAuthThrottledException;
use Contena\Core\System\User\Exception\UserNotFoundByHashException;
use Contena\Core\System\User\Exception\UserOptinNotCompletedException;
use Contena\Core\System\User\Exception\UserRecoveryHashExpiredException;
use Contena\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\User\Exception\BadCredentialsException;
use Contena\Core\System\User\Exception\UserNotFoundByIdException;
use Contena\Core\System\User\Exception\UserNotFoundException;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a channel-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class AuthController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountLoginPageLoader $loginPageLoader,
        private readonly AccountRecoverPasswordPageLoader $recoverPasswordPageLoader,
        private readonly SystemConfigService $systemConfigService
    ) {
    }



    #[Route(
        path: '/account/login',
        name: 'frontend.account.login.page',
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET]
    )]
    public function loginPage(Request $request, RequestDataBag $data, ChannelContext $context): Response
    {
        // Add '_httpCache' => true, to defaults in Route and remove _noStore
        if (Feature::isActive('PERFORMANCE_TWEAKS')) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
            $request->attributes->remove(PlatformRequest::ATTRIBUTE_NO_STORE);
        }

        $user = $context->getUser();

        $redirect = (string) $request->query->get('redirectTo', $user?->getGuest() ? 'frontend.account.logout.page' : 'frontend.account.home.page');

        if ($user !== null) {
            $request->request->set('redirectTo', $redirect);

            return $this->createActionResponse($request);
        }

        $page = $this->loginPageLoader->load($request, $context);

        $this->hook(new AccountLoginPageLoadedHook($page, $context));

        return $this->renderFrontend('@Frontend/frontend/page/account/register/index.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $request->query->all()['redirectParameters'] ?? json_encode([]),
            'errorRoute' => $request->attributes->get('_route'),
            'page' => $page,
            'loginError' => $request->attributes->getBoolean('loginError'),
            'waitTime' => $request->attributes->get('waitTime'),
            'errorSnippet' => $request->attributes->get('errorSnippet'),
            'data' => $data,
        ]);
    }

    #[Route(
        path: '/account/guest/login',
        name: 'frontend.account.guest.login.page',
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET]
    )]
    public function guestLoginPage(Request $request, ChannelContext $context): Response
    {
        /** @var string|null $redirect */
        $redirect = $request->query->get('redirectTo');
        if (!$redirect) {
            // page was probably called directly
            $this->addFlash(self::DANGER, $this->trans('account.orderGuestLoginWrongCredentials'));

            return $this->redirectToRoute('frontend.account.login.page');
        }

        $user = $context->getUser();

        if ($user !== null) {
            $request->request->set('redirectTo', $redirect);

            return $this->createActionResponse($request);
        }

        $redirectParameters = $request->query->all()['redirectParameters'] ?? [];
        if (!\is_array($redirectParameters)) {
            $redirectParameters = [];
        }

        // The template builds the form action via `path(redirectTo, redirectParameters)`. If the redirect target
        // cannot be generated (e.g. the page was called directly without the required `redirectParameters`),
        // rendering would fail with a 500. Behave like a direct call and send the admin user to the login page instead.
        try {
            $this->generateUrl($redirect, $redirectParameters);
        } catch (RouteNotFoundException|MissingMandatoryParametersException|InvalidParameterException) {
            $this->addFlash(self::DANGER, $this->trans('account.orderGuestLoginWrongCredentials'));

            return $this->redirectToRoute('frontend.account.login.page');
        }

        // WaitTime can be either set as attribute when it's forwarded to this route
        // or as query parameter when it's redirected
        $waitTime = (int) ($request->attributes->get('waitTime') ?? $request->query->get('waitTime'));
        if ($waitTime) {
            $this->addFlash(self::INFO, $this->trans('account.loginThrottled', ['%seconds%' => $waitTime]));
        }

        // loginError can be either set as attribute when it's forwarded to this route
        // or as query parameter when it's redirected
        if ($request->attributes->getBoolean('loginError') || $request->query->getBoolean('loginError')) {
            $this->addFlash(self::DANGER, $this->trans('account.orderGuestLoginWrongCredentials'));
        }

        $page = $this->loginPageLoader->load($request, $context);

        $this->hook(new AccountGuestLoginPageLoadedHook($page, $context));

        return $this->renderFrontend('@Frontend/frontend/page/account/guest-auth.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $redirectParameters,
            'page' => $page,
        ]);
    }

    #[Route(
        path: '/account/logout',
        name: 'frontend.account.logout.page',
        methods: [Request::METHOD_GET]
    )]
    public function logout(Request $request, ChannelContext $context, RequestDataBag $dataBag): Response
    {
        if ($context->getUser() === null) {
            return $this->redirectToRoute('frontend.account.login.page');
        }

        try {
            $this->logoutRoute->logout($context, $dataBag);
            $this->addFlash(self::SUCCESS, $this->trans('account.logoutSucceeded'));

            $request->attributes->set(PlatformRequest::ATTRIBUTE_CLEAR_SITE_DATA, true);

            $parameters = [];
        } catch (ConstraintViolationException $formViolations) {
            $parameters = ['formViolations' => $formViolations];
        }

        return $this->redirectToRoute('frontend.account.login.page', $parameters);
    }

    #[Route(
        path: '/account/login',
        name: 'frontend.account.login',
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_POST]
    )]
    public function login(Request $request, RequestDataBag $data, ChannelContext $context): Response
    {
        $user = $context->getUser();

        if ($user !== null && $user->getGuest() === false) {
            return $this->createActionResponse($request);
        }

        try {
            $token = $this->loginRoute->login($data, $context)->getToken();
            $cartBeforeNewContext = $this->cartFacade->get($token, $context);

            if ($token !== '') {
                $this->addCartErrors($cartBeforeNewContext);

                return $this->createActionResponse($request);
            }
        } catch (UserOptinNotCompletedException $e) {
            $errorSnippet = $e->getSnippetKey();
        } catch (UserAuthThrottledException $e) {
            $waitTime = $e->getWaitTime();
        } catch (BadCredentialsException|UserNotFoundException) {
        } catch (PasswordPoliciesUpdatedException) {
            $this->addFlash(self::WARNING, $this->trans('account.passwordPoliciesUpdated'));

            return $this->forwardToRoute('frontend.account.recover.page');
        } finally {
            $data->set('password', null);
        }

        // Keep a failed login within the checkout flow instead of forwarding to the account login page.
        $redirectTo = $request->request->getString('redirectTo');
        $errorRoute = str_starts_with($redirectTo, 'frontend.checkout')
            ? 'frontend.checkout.register.page'
            : 'frontend.account.login.page';

        return $this->forwardToRoute(
            $errorRoute,
            [
                'loginError' => true,
                'errorSnippet' => $errorSnippet ?? null,
                'waitTime' => $waitTime ?? null,
            ]
        );
    }

    #[Route(
        path: '/account/recover',
        name: 'frontend.account.recover.page',
        methods: [Request::METHOD_GET]
    )]
    public function recoverAccountForm(Request $request, ChannelContext $context): Response
    {
        // Add '_httpCache' => true, to defaults in Route
        if (Feature::isActive('PERFORMANCE_TWEAKS')) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
            $request->attributes->remove(PlatformRequest::ATTRIBUTE_NO_STORE);
        }

        $page = $this->loginPageLoader->load($request, $context);

        return $this->renderFrontend('@Frontend/frontend/page/account/profile/recover-password.html.twig', [
            'page' => $page,
        ]);
    }

    #[Route(
        path: '/account/recover',
        name: 'frontend.account.recover.request',
        methods: [Request::METHOD_POST]
    )]
    public function generateAccountRecovery(Request $request, RequestDataBag $data, ChannelContext $context): Response
    {
        try {
            $mailData = $data->get('email');
            if (!$mailData instanceof DataBag) {
                throw RoutingException::invalidRequestParameter('email');
            }
            $mailData->set('frontendUrl', $request->attributes->get(RequestTransformer::FRONTEND_URL));

            $this->sendPasswordRecoveryMailRoute->sendRecoveryMail(
                $mailData->toRequestDataBag(),
                $context,
                false
            );

            $this->addFlash(self::SUCCESS, $this->trans('account.recoveryMailSend'));
        } catch (UserNotFoundException) {
            $this->addFlash(self::SUCCESS, $this->trans('account.recoveryMailSend'));
        } catch (InconsistentCriteriaIdsException) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        } catch (RateLimitExceededException $e) {
            $this->addFlash(self::INFO, $this->trans('error.rateLimitExceeded', ['%seconds%' => $e->getWaitTime()]));
        } catch (ConstraintViolationException $formViolations) {
            return $this->forwardToRoute(
                'frontend.account.recover.page',
                ['formViolations' => $formViolations]
            );
        }

        return $this->redirectToRoute('frontend.account.recover.page');
    }

    #[Route(
        path: '/account/recover/password',
        name: 'frontend.account.recover.password.page',
        methods: [Request::METHOD_GET]
    )]
    public function resetPasswordForm(Request $request, ChannelContext $context): Response
    {
        $hash = $request->query->get('hash');

        if (!$hash || !\is_string($hash)) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordHashNotFound'));

            return $this->redirectToRoute('frontend.account.recover.request');
        }

        try {
            $page = $this->recoverPasswordPageLoader->load($request, $context, $hash);
        } catch (ConstraintViolationException|UserNotFoundByHashException) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordHashNotFound'));

            return $this->redirectToRoute('frontend.account.recover.request');
        }

        $this->hook(new AccountRecoverPasswordPageLoadedHook($page, $context));

        if ($page->getHash() === null || $page->isHashExpired()) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordHashNotFound'));

            return $this->redirectToRoute('frontend.account.recover.request');
        }

        return $this->renderFrontend('@Frontend/frontend/page/account/profile/reset-password.html.twig', [
            'page' => $page,
            'formViolations' => $request->attributes->get('formViolations') ?? ($request->query->all()['formViolations'] ?? null),
        ]);
    }

    #[Route(
        path: '/account/recover/password',
        name: 'frontend.account.recover.password.reset',
        methods: [Request::METHOD_POST]
    )]
    public function resetPassword(RequestDataBag $data, ChannelContext $context): Response
    {
        $passwordData = $data->get('password');
        if (!$passwordData instanceof DataBag) {
            throw RoutingException::invalidRequestParameter('password');
        }
        $hash = $passwordData->get('hash');

        try {
            $this->resetPasswordRoute->resetPassword($passwordData->toRequestDataBag(), $context);

            $this->addFlash(self::SUCCESS, $this->trans('account.passwordChangeSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            if ($formViolations->getViolations('newPassword')->count() === 1) {
                $this->addFlash(self::DANGER, $this->trans('account.passwordNotIdentical'));
            } else {
                $this->addFlash(self::DANGER, $this->trans('account.passwordChangeNoSuccess'));
            }

            return $this->forwardToRoute(
                'frontend.account.recover.password.page',
                ['hash' => $hash, 'formViolations' => $formViolations, 'passwordFormViolation' => true]
            );
        } catch (UserNotFoundByHashException) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordChangeNoSuccess'));

            return $this->forwardToRoute('frontend.account.recover.request');
        } catch (UserRecoveryHashExpiredException) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordHashExpired'));

            return $this->forwardToRoute('frontend.account.recover.request');
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    #[Route(
        path: '/account/login/imitate-user',
        name: 'frontend.account.login.imitate-user',
        methods: [Request::METHOD_POST]
    )]
    public function imitateUserLogin(RequestDataBag $data, ChannelContext $context): Response
    {
        try {
            $this->imitateUserRoute->imitateUserLogin($data, $context);

            return $this->redirectToRoute('frontend.account.home.page');
        } catch (InvalidImitateUserTokenException|UserNotFoundByIdException) {
            return $this->forwardToRoute(
                'frontend.account.login.page',
                [
                    'loginError' => true,
                ]
            );
        }
    }

    #[Route(
        path: '/account/convert',
        name: 'frontend.account.convert.page',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_GET],
    )]
    public function convertForm(ChannelContext $context): Response
    {
        $user = $context->getUser();

        if ($user === null || !$user->getGuest()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        return $this->renderFrontend('@Frontend/frontend/page/account/convert.html.twig');
    }

    #[Route(
        path: '/account/convert',
        name: 'frontend.account.convert.save',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
            PlatformRequest::ATTRIBUTE_CAPTCHA => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function convert(RequestDataBag $request, ChannelContext $context): Response
    {
        $user = $context->getUser();

        if ($user === null || !$user->getGuest()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        $definition = new DataValidationDefinition('guest_conversion');

        if ($this->systemConfigService->get('core.loginRegistration.requirePasswordConfirmation', $context->getChannelId())) {
            $definition->add('passwordConfirmation', new NotBlank(), new EqualTo(value: $request->get('password')));
        }

        try {
            $this->convertGuestRoute->convertGuest($request, $context, $user, $definition);
        } catch (RateLimitExceededException $e) {
            $this->addFlash(self::INFO, $this->trans('error.rateLimitExceeded', ['%seconds%' => $e->getWaitTime()]));

            return $this->forwardToRoute(
                'frontend.account.convert.page',
            );
        } catch (ConstraintViolationException $formViolations) {
            if ($formViolations->getViolations()->findByCodes(UserEmailUnique::CUSTOMER_EMAIL_NOT_UNIQUE_CODE)->count()) {
                $this->addFlash(
                    self::DANGER,
                    $this->trans(
                        \sprintf('error.%s', UserEmailUnique::CUSTOMER_EMAIL_NOT_UNIQUE_CODE)
                    )
                );
            }

            return $this->forwardToRoute(
                'frontend.account.convert.page',
                ['formViolations' => $formViolations]
            );
        }

        $this->addFlash(self::SUCCESS, $this->trans('account.convertSucceeded'));

        return $this->redirectToRoute('frontend.account.home.page');
    }
}
