<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\UserEntity;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Page\Account\Profile\AccountProfilePageLoadedHook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Frontend\Page\Account\Profile\AccountProfilePageLoader;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a channel-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class AccountProfileController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountProfilePageLoader $profilePageLoader,
        private readonly LoggerInterface $logger
    ) {
    }



    #[Route(
        path: '/account/profile',
        name: 'frontend.account.profile.page',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function profileOverview(Request $request, ChannelContext $context): Response
    {
        $page = $this->profilePageLoader->load($request, $context);

        $this->hook(new AccountProfilePageLoadedHook($page, $context));

        return $this->renderFrontend('@Frontend/frontend/page/account/profile/index.html.twig', [
            'page' => $page,
            'passwordFormViolation' => $request->attributes->get('passwordFormViolation'),
            'emailFormViolation' => $request->attributes->get('emailFormViolation'),
            'profileFormData' => $request->attributes->get('profileFormData'),
            'redirectTo' => RequestParamHelper::get($request, 'redirectTo'),
        ]);
    }

    #[Route(
        path: '/account/profile',
        name: 'frontend.account.profile.save',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function saveProfile(RequestDataBag $data, ChannelContext $context, UserEntity $user, Request $request): Response
    {
        try {
            $this->changeUserProfileRoute->change($data, $context, $user);

            $this->addFlash(self::SUCCESS, $this->trans('account.profileUpdateSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            return $this->forwardToRoute('frontend.account.profile.page', ['formViolations' => $formViolations, 'profileFormData' => $data]);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), ['e' => $exception]);
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        if (RequestParamHelper::get($request, 'redirectTo') || RequestParamHelper::get($request, 'forwardTo')) {
            return $this->createActionResponse($request);
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    #[Route(
        path: '/account/profile/email',
        name: 'frontend.account.profile.email.save',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function saveEmail(RequestDataBag $data, ChannelContext $context, UserEntity $user): Response
    {
        try {
            $emailParam = $data->get('email');
            if (!$emailParam instanceof RequestDataBag) {
                throw RoutingException::missingRequestParameter('email');
            }
            $this->changeEmailRoute->change($emailParam->toRequestDataBag(), $context, $user);

            $this->addFlash(self::SUCCESS, $this->trans('account.emailChangeSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            $this->addFlash(self::DANGER, $this->trans('account.emailChangeNoSuccess'));

            return $this->forwardToRoute('frontend.account.profile.page', ['formViolations' => $formViolations, 'emailFormViolation' => true]);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage(), ['e' => $exception]);
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    #[Route(
        path: '/account/profile/password',
        name: 'frontend.account.profile.password.save',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function savePassword(RequestDataBag $data, ChannelContext $context, UserEntity $user, Request $request): Response
    {
        try {
            $passwordParam = $data->get('password');
            if (!$passwordParam instanceof RequestDataBag) {
                throw RoutingException::missingRequestParameter('password');
            }
            $this->changePasswordRoute->change($passwordParam->toRequestDataBag(), $context, $user);

            $this->addFlash(self::SUCCESS, $this->trans('account.passwordChangeSuccess'));
        } catch (ConstraintViolationException $formViolations) {
            $this->addFlash(self::DANGER, $this->trans('account.passwordChangeNoSuccess'));

            return $this->forwardToRoute('frontend.account.profile.page', ['formViolations' => $formViolations, 'passwordFormViolation' => true]);
        }

        if (RequestParamHelper::get($request, 'redirectTo') || RequestParamHelper::get($request, 'forwardTo')) {
            return $this->createActionResponse($request);
        }

        return $this->redirectToRoute('frontend.account.profile.page');
    }

    #[Route(
        path: '/account/profile/delete',
        name: 'frontend.account.profile.delete',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function deleteProfile(Request $request, ChannelContext $context, UserEntity $user): Response
    {
        try {
            $this->deleteUserRoute->delete($context, $user);
            $this->addFlash(self::SUCCESS, $this->trans('account.profileDeleteSuccessAlert'));
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), ['e' => $exception]);
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        if (RequestParamHelper::get($request, 'redirectTo') || RequestParamHelper::get($request, 'forwardTo')) {
            return $this->createActionResponse($request);
        }

        return $this->redirectToRoute('frontend.home.page');
    }
}
