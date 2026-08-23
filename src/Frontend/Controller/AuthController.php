<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Channel\AbstractImitateMemberRoute;
use Contena\Core\System\Member\Channel\AbstractLoginRoute;
use Contena\Core\System\Member\Channel\AbstractLogoutRoute;
use Contena\Core\System\Member\Channel\AbstractResetPasswordRoute;
use Contena\Core\System\Member\Channel\AbstractSendPasswordRecoveryMailRoute;
use Contena\Core\System\Member\Exception\BadCredentialsException;
use Contena\Core\System\Member\Exception\InvalidImitateMemberTokenException;
use Contena\Core\System\Member\Exception\MemberAuthThrottledException;
use Contena\Core\System\Member\Exception\MemberNotFoundByHashException;
use Contena\Core\System\Member\Exception\MemberNotFoundByIdException;
use Contena\Core\System\Member\Exception\MemberNotFoundException;
use Contena\Core\System\Member\Exception\MemberOptinNotCompletedException;
use Contena\Core\System\Member\Exception\MemberRecoveryHashExpiredException;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
        private readonly AbstractSendPasswordRecoveryMailRoute $sendPasswordRecoveryMailRoute,
        private readonly AbstractResetPasswordRoute $resetPasswordRoute,
        private readonly AbstractLoginRoute $loginRoute,
        private readonly AbstractLogoutRoute $logoutRoute,
        private readonly AbstractImitateMemberRoute $imitateMemberRoute,
        private readonly AccountRecoverPasswordPageLoader $recoverPasswordPageLoader,
    ) {
    }

    #[Route(
        path: '/account/login',
        name: 'frontend.account.login.page',
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET],
    )]
    public function loginPage(Request $request, RequestDataBag $data, ChannelContext $context): Response
    {
        $member = $context->getMember();
        $redirect = (string) $request->query->get('redirectTo', 'frontend.account.home.page');

        if ($member !== null) {
            $request->request->set('redirectTo', $redirect);

            return $this->createActionResponse($request);
        }

        $page = $this->loginPageLoader->load($request, $context);

        return $this->renderFrontend('@Frontend/frontend/page/account/login.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $request->query->all()['redirectParameters'] ?? json_encode([]),
            'page' => $page,
            'loginError' => $request->attributes->getBoolean('loginError'),
            'waitTime' => $request->attributes->get('waitTime'),
            'errorSnippet' => $request->attributes->get('errorSnippet'),
            'data' => $data,
        ]);
    }

    #[Route(path: '/account/logout', name: 'frontend.account.logout.page', methods: [Request::METHOD_GET])]
    public function logout(Request $request, ChannelContext $context, RequestDataBag $dataBag): Response
    {
        if ($context->getMember() === null) {
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

    #[Route(path: '/account/login', name: 'frontend.account.login', defaults: ['XmlHttpRequest' => true], methods: [Request::METHOD_POST])]
    public function login(Request $request, RequestDataBag $data, ChannelContext $context): Response
    {
        if ($context->getMember() !== null) {
            return $this->createActionResponse($request);
        }

        try {
            $token = $this->loginRoute->login($data, $context)->getToken();

            if ($token !== '') {
                return $this->createActionResponse($request);
            }
        } catch (MemberOptinNotCompletedException $e) {
            $errorSnippet = $e->getSnippetKey();
        } catch (MemberAuthThrottledException $e) {
            $waitTime = $e->getWaitTime();
        } catch (BadCredentialsException|MemberNotFoundException) {
        } finally {
            $data->set('password', null);
        }

        return $this->forwardToRoute('frontend.account.login.page', [
            'loginError' => true,
            'errorSnippet' => $errorSnippet ?? null,
            'waitTime' => $waitTime ?? null,
        ]);
    }

    #[Route(path: '/account/recover', name: 'frontend.account.recover.page', defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true], methods: [Request::METHOD_GET])]
    public function recoverAccountForm(Request $request, ChannelContext $context): Response
    {
        $page = $this->loginPageLoader->load($request, $context);

        return $this->renderFrontend('@Frontend/frontend/page/account/recover-password.html.twig', ['page' => $page]);
    }

    #[Route(path: '/account/recover', name: 'frontend.account.recover.request', methods: [Request::METHOD_POST])]
    public function generateAccountRecovery(Request $request, RequestDataBag $data, ChannelContext $context): Response
    {
        try {
            $mailData = $data->get('email');
            if (!$mailData instanceof DataBag) {
                throw RoutingException::invalidRequestParameter('email');
            }
            $mailData->set('frontendUrl', $request->attributes->get(RequestTransformer::FRONTEND_URL));

            $this->sendPasswordRecoveryMailRoute->sendRecoveryMail($mailData->toRequestDataBag(), $context, false);
            $this->addFlash(self::SUCCESS, $this->trans('account.recoveryMailSend'));
        } catch (MemberNotFoundException) {
            $this->addFlash(self::SUCCESS, $this->trans('account.recoveryMailSend'));
        } catch (InconsistentCriteriaIdsException) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        } catch (RateLimitExceededException $e) {
            $this->addFlash(self::INFO, $this->trans('error.rateLimitExceeded', ['%seconds%' => $e->getWaitTime()]));
        } catch (ConstraintViolationException $formViolations) {
            return $this->forwardToRoute('frontend.account.recover.page', ['formViolations' => $formViolations]);
        }

        return $this->redirectToRoute('frontend.account.recover.page');
    }

    #[Route(path: '/account/recover/password', name: 'frontend.account.recover.password.page', methods: [Request::METHOD_GET])]
    public function resetPasswordForm(Request $request, ChannelContext $context): Response
    {
        $hash = $request->query->get('hash');

        if (!$hash || !\is_string($hash)) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordHashNotFound'));

            return $this->redirectToRoute('frontend.account.recover.request');
        }

        try {
            $page = $this->recoverPasswordPageLoader->load($request, $context, $hash);
        } catch (ConstraintViolationException|MemberNotFoundByHashException) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordHashNotFound'));

            return $this->redirectToRoute('frontend.account.recover.request');
        }

        if ($page->getHash() === null || $page->isHashExpired()) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordHashNotFound'));

            return $this->redirectToRoute('frontend.account.recover.request');
        }

        return $this->renderFrontend('@Frontend/frontend/page/account/reset-password.html.twig', [
            'page' => $page,
            'formViolations' => $request->attributes->get('formViolations') ?? ($request->query->all()['formViolations'] ?? null),
        ]);
    }

    #[Route(path: '/account/recover/password', name: 'frontend.account.recover.password.reset', methods: [Request::METHOD_POST])]
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

            return $this->forwardToRoute('frontend.account.recover.password.page', [
                'hash' => $hash,
                'formViolations' => $formViolations,
                'passwordFormViolation' => true,
            ]);
        } catch (MemberNotFoundByHashException) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordChangeNoSuccess'));

            return $this->forwardToRoute('frontend.account.recover.request');
        } catch (MemberRecoveryHashExpiredException) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordHashExpired'));

            return $this->forwardToRoute('frontend.account.recover.request');
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    #[Route(path: '/account/login/imitate-member', name: 'frontend.account.login.imitate-member', methods: [Request::METHOD_POST])]
    public function imitateMemberLogin(RequestDataBag $data, ChannelContext $context): Response
    {
        try {
            $this->imitateMemberRoute->imitateMemberLogin($data, $context);

            return $this->redirectToRoute('frontend.account.home.page');
        } catch (InvalidImitateMemberTokenException|MemberNotFoundByIdException) {
            return $this->forwardToRoute('frontend.account.login.page', ['loginError' => true]);
        }
    }
}
