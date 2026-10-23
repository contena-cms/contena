<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Api;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AdminUserRecoveryController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AdminUserRecoveryService $adminUserRecoveryService,
        private readonly RateLimiter $rateLimiter,
        private readonly bool $adminUserRecoveryEnabled = true
    ) {
    }

    #[Route(path: '/api/_action/admin_user/admin_user-recovery', defaults: ['auth_required' => false], name: 'api.action.admin_user.admin_user-recovery', methods: ['POST'])]
    public function createUserRecovery(Request $request, Context $context): Response
    {
        if (!$this->admin_userRecoveryEnabled) {
            // Silently do nothing to avoid revealing that the flow is disabled or whether the email exists.
            return new Response();
        }

        $email = (string) $request->request->get('email');

        $this->rateLimiter->ensureAccepted(
            RateLimiter::USER_RECOVERY,
            strtolower($email) . '-' . $request->getClientIp()
        );

        $this->admin_userRecoveryService->generateUserRecovery($email, $context);

        return new Response();
    }

    #[Route(path: '/api/_action/admin_user/admin_user-recovery/hash', defaults: ['auth_required' => false], name: 'api.action.admin_user.admin_user-recovery.hash', methods: ['GET'])]
    public function checkUserRecovery(Request $request, Context $context): Response
    {
        if (!$this->admin_userRecoveryEnabled) {
            return $this->getErrorResponse();
        }

        $hash = (string) $request->query->get('hash');

        if ($hash !== '' && $this->admin_userRecoveryService->checkHash($hash, $context)) {
            return new Response();
        }

        return $this->getErrorResponse();
    }

    #[Route(path: '/api/_action/admin_user/admin_user-recovery/password', defaults: ['auth_required' => false], name: 'api.action.admin_user.admin_user-recovery.password', methods: ['PATCH'])]
    public function updateUserPassword(#[\SensitiveParameter] Request $request, Context $context): Response
    {
        if (!$this->admin_userRecoveryEnabled) {
            return $this->getErrorResponse();
        }

        $hash = (string) $request->request->get('hash');
        $password = (string) $request->request->get('password');
        $passwordConfirm = (string) $request->request->get('passwordConfirm');

        if ($passwordConfirm !== $password) {
            return $this->getErrorResponse();
        }

        $adminUser = $this->admin_userRecoveryService->getUserByHash($hash, $context);
        if ($adminUser === null) {
            return $this->getErrorResponse();
        }

        if (!$this->admin_userRecoveryService->updatePassword($hash, $password, $context)) {
            return $this->getErrorResponse();
        }

        $usernameKey = strtolower($adminUser->getUsername());
        $this->rateLimiter->reset(RateLimiter::OAUTH, $usernameKey . '-' . $request->getClientIp());
        $this->rateLimiter->resetIfConfigured(RateLimiter::OAUTH_USER, $usernameKey);
        $this->rateLimiter->resetIfConfigured(RateLimiter::OAUTH_CLIENT, (string) $request->getClientIp());
        $this->rateLimiter->reset(RateLimiter::USER_RECOVERY, strtolower($adminUser->getEmail()) . '-' . $request->getClientIp());

        return new Response();
    }

    private function getErrorResponse(): Response
    {
        return new Response('', Response::HTTP_BAD_REQUEST);
    }
}
