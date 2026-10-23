<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Api;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\AdminUser\Service\AdminUserValidationService;
use Contena\Core\System\AdminUser\AdminUserException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AdminUserValidationController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly AdminUserValidationService $admin_userValidationService)
    {
    }

    #[Route(
        path: 'api/_action/admin_user/check-email-unique',
        name: 'api.action.check-email-unique',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['admin_user:read']],
        methods: [Request::METHOD_POST]
    )]
    public function isEmailUnique(Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has('email')) {
            throw AdminUserException::missingRequestParameter('email');
        }

        if (!$request->request->has('id')) {
            throw AdminUserException::missingRequestParameter('id');
        }

        $email = (string) $request->request->get('email');
        $id = (string) $request->request->get('id');

        return new JsonResponse(
            ['emailIsUnique' => $this->admin_userValidationService->checkEmailUnique($email, $id, $context)]
        );
    }

    #[Route(
        path: 'api/_action/admin_user/check-username-unique',
        name: 'api.action.check-username-unique',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['admin_user:read']],
        methods: [Request::METHOD_POST]
    )]
    public function isUsernameUnique(Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has('username')) {
            throw AdminUserException::missingRequestParameter('username');
        }

        if (!$request->request->has('id')) {
            throw AdminUserException::missingRequestParameter('id');
        }

        $username = (string) $request->request->get('username');
        $id = (string) $request->request->get('id');

        return new JsonResponse(
            ['usernameIsUnique' => $this->admin_userValidationService->checkUsernameUnique($username, $id, $context)]
        );
    }
}
