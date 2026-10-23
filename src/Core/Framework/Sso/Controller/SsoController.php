<?php declare(strict_types=1);

namespace Contena\Core\Framework\Sso\Controller;

use League\OAuth2\Server\AuthorizationServer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Routing\TrustedOriginUrlGenerator;
use Contena\Core\Framework\Sso\SsoAdminUser\SsoAdminUserInvitationMailService;
use Contena\Core\Framework\Sso\SsoAdminUser\SsoAdminUserService;
use Contena\Core\Framework\Sso\StateValidator;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Contena\Core\Framework\Sso\Config\LoginConfigService;
use Contena\Core\Framework\Sso\LoginResponseService;
use Contena\Core\Framework\Sso\SsoService;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class SsoController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AuthorizationServer $authorizationServer,
        private readonly PsrHttpFactory $psrHttpFactory,
        private readonly LoginConfigService $loginConfigService,
        private readonly LoginResponseService $loginResponseService,
        private readonly StateValidator $stateValidator,
        private readonly SsoAdminUserService $ssoAdminUserService,
        private readonly SsoAdminUserInvitationMailService $ssoAdminUserInvitationMailService,
        private readonly SsoService $ssoService,
        private readonly RouterInterface $router
    ) {
    }



    #[Route(
        path: '/api/oauth/sso/config',
        name: 'api.oauth.sso.config',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function loadSsoLoginConfig(Request $request): JsonResponse
    {
        $random = $this->stateValidator->createRandom($request);
        $templateData = $this->loginConfigService->createTemplateData($random);

        return new JsonResponse($templateData);
    }

    #[Route(
        path: '/api/oauth/sso/code',
        name: 'api.oauth.sso.code',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function callbackWithCode(Request $request): Response
    {
        $this->stateValidator->validateRequest($request);

        $psr7Request = $this->psrHttpFactory->createRequest($request);
        $psr7Response = $this->psrHttpFactory->createResponse(new Response());

        try {
            $response = $this->authorizationServer->respondToAccessTokenRequest($psr7Request, $psr7Response);
            // @phpstan-ignore catch.neverThrown (SsoUserNotFoundException is thrown inside an external library where we implemented an interface)
        } catch (SsoUserNotFoundException $ssoException) {
            return $this->loginResponseService->createErrorResponse($ssoException->getEmail());
        }

        return $this->loginResponseService->create($response);
    }

    #[Route(
        path: '/api/oauth/sso/auth',
        name: 'oauth.sso.auth',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function ssoAuth(Request $request): RedirectResponse
    {
        $random = $request->getSession()->get(StateValidator::SESSION_KEY);
        if ($random === null) {
            return $this->redirect(TrustedOriginUrlGenerator::generateAdministrationUrl($this->router));
        }

        $url = $this->loginConfigService->createRedirectUrl($random, $request->query->getBoolean('usePromptLogin'));

        return $this->redirect($url);
    }

    #[Route(
        path: '/api/_info/is-sso',
        name: 'api.info.is-sso',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration']],
        methods: [Request::METHOD_GET]
    )]
    public function isSso(): JsonResponse
    {
        return new JsonResponse(['isSso' => $this->ssoService->isSso()]);
    }

    #[Route(
        path: '/api/_action/sso/invite-admin-user',
        name: 'api.action.sso.invite-admin-user',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'], PlatformRequest::ATTRIBUTE_ACL => ['admin_user:create']],
        methods: [Request::METHOD_POST]
    )]
    public function inviteUser(RequestDataBag $requestDataBag, Context $context): JsonResponse
    {
        $email = $requestDataBag->get('email');
        $localeId = $requestDataBag->get('localeId');

        $this->ssoAdminUserService->inviteUser($email, $localeId, $context);
        $this->ssoAdminUserInvitationMailService->sendInvitationMailToUser($email, $localeId, $context);

        return new JsonResponse();
    }
}
