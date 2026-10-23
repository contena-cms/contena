<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\AdminUser\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\System\AdminUser\Api\AdminUserRecoveryController;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryService;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AdminUserRecoveryController::class)]
class AdminUserRecoveryControllerTest extends TestCase
{
    public function testUpdateUserPasswordResetsAllRateLimiters(): void
    {
        $username = 'Admin';
        $email = 'admin@example.com';
        $ip = '10.0.0.1';
        $expectedUsernameKey = strtolower($username);

        $admin_user = new AdminUserEntity();
        $admin_user->setUsername($username);
        $admin_user->setEmail($email);

        $admin_userRecoveryService = $this->createMock(AdminUserRecoveryService::class);
        $admin_userRecoveryService->expects($this->once())->method('getUserByHash')->willReturn($admin_user);
        $admin_userRecoveryService->expects($this->once())->method('updatePassword')->willReturn(true);

        $resetCalls = [];
        $resetIfConfiguredCalls = [];

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->exactly(2))
            ->method('reset')
            ->willReturnCallback(function (string $route, string $key) use (&$resetCalls): void {
                $resetCalls[] = [$route, $key];
            });
        $rateLimiter->expects($this->exactly(2))
            ->method('resetIfConfigured')
            ->willReturnCallback(function (string $route, string $key) use (&$resetIfConfiguredCalls): void {
                $resetIfConfiguredCalls[] = [$route, $key];
            });

        $controller = new AdminUserRecoveryController($admin_userRecoveryService, $rateLimiter);

        $request = new Request(server: ['REMOTE_ADDR' => $ip]);
        $request->request->set('hash', 'some-hash');
        $request->request->set('password', 'newPassword123');
        $request->request->set('passwordConfirm', 'newPassword123');

        $response = $controller->updateUserPassword($request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        static::assertSame([
            [RateLimiter::OAUTH, $expectedUsernameKey . '-' . $ip],
            [RateLimiter::USER_RECOVERY, strtolower($email) . '-' . $ip],
        ], $resetCalls);

        static::assertSame([
            [RateLimiter::OAUTH_USER, $expectedUsernameKey],
            [RateLimiter::OAUTH_CLIENT, $ip],
        ], $resetIfConfiguredCalls);
    }

    public function testCreateUserRecoveryIsSilentNoOpWhenDisabled(): void
    {
        $admin_userRecoveryService = $this->createMock(AdminUserRecoveryService::class);
        $admin_userRecoveryService->expects($this->never())->method('generateUserRecovery');

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('ensureAccepted');

        $controller = new AdminUserRecoveryController($admin_userRecoveryService, $rateLimiter, false);

        $request = new Request();
        $request->request->set('email', 'admin@example.com');

        $response = $controller->createUserRecovery($request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testCheckUserRecoveryIsRejectedWhenDisabled(): void
    {
        $admin_userRecoveryService = $this->createMock(AdminUserRecoveryService::class);
        $admin_userRecoveryService->expects($this->never())->method('checkHash');

        $controller = new AdminUserRecoveryController(
            $admin_userRecoveryService,
            static::createStub(RateLimiter::class),
            false,
        );

        $request = new Request();
        $request->query->set('hash', 'some-hash');

        $response = $controller->checkUserRecovery($request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testUpdateUserPasswordIsRejectedWhenDisabled(): void
    {
        $admin_userRecoveryService = $this->createMock(AdminUserRecoveryService::class);
        $admin_userRecoveryService->expects($this->never())->method('getUserByHash');
        $admin_userRecoveryService->expects($this->never())->method('updatePassword');

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('reset');
        $rateLimiter->expects($this->never())->method('resetIfConfigured');

        $controller = new AdminUserRecoveryController($admin_userRecoveryService, $rateLimiter, false);

        $request = new Request();
        $request->request->set('hash', 'some-hash');
        $request->request->set('password', 'newPassword123');
        $request->request->set('passwordConfirm', 'newPassword123');

        $response = $controller->updateUserPassword($request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRateLimitersNotResetWhenPasswordsDontMatch(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('reset');
        $rateLimiter->expects($this->never())->method('resetIfConfigured');

        $controller = new AdminUserRecoveryController(
            static::createStub(AdminUserRecoveryService::class),
            $rateLimiter,
        );

        $request = new Request();
        $request->request->set('hash', 'some-hash');
        $request->request->set('password', 'pass1');
        $request->request->set('passwordConfirm', 'pass2');

        $response = $controller->updateUserPassword($request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRateLimitersNotResetWhenUserNotFound(): void
    {
        $admin_userRecoveryService = $this->createMock(AdminUserRecoveryService::class);
        $admin_userRecoveryService->expects($this->once())->method('getUserByHash')->willReturn(null);

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('reset');
        $rateLimiter->expects($this->never())->method('resetIfConfigured');

        $controller = new AdminUserRecoveryController($admin_userRecoveryService, $rateLimiter);

        $request = new Request();
        $request->request->set('hash', 'some-hash');
        $request->request->set('password', 'pass');
        $request->request->set('passwordConfirm', 'pass');

        $response = $controller->updateUserPassword($request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }
}
