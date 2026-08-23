<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Member\Channel\AccountService;
use Contena\Core\System\Member\Channel\LoginRoute;
use Contena\Core\System\Member\MemberException;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(LoginRoute::class)]
class LoginRouteTest extends TestCase
{
    public function testRateLimiterIsCalledWithCorrectKeys(): void
    {
        $email = 'Test@Example.COM';
        $ip = '192.168.0.1';
        $expectedEmailKey = strtolower($email);
        $expectedCombinedKey = $expectedEmailKey . '-' . $ip;
        $tenantId = 'tenant-a';
        $context = Generator::generateChannelContext(Context::createTenantContext($tenantId));

        $ensureAcceptedCalls = [];
        $ensureIfConfiguredCalls = [];

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->willReturnCallback(function (string $route, string $key, Context $context) use (&$ensureAcceptedCalls): void {
                $ensureAcceptedCalls[] = [$route, $key, $context->getTenantId()];
            });
        $rateLimiter->expects($this->exactly(2))
            ->method('ensureAcceptedIfConfigured')
            ->willReturnCallback(function (string $route, string $key, Context $context) use (&$ensureIfConfiguredCalls): void {
                $ensureIfConfiguredCalls[] = [$route, $key, $context->getTenantId()];
            });

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects($this->once())->method('loginByCredentials')->willReturn('test-token');

        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => $ip]));

        $route = new LoginRoute($accountService, $requestStack, $rateLimiter);
        $route->login(new RequestDataBag(['email' => $email, 'password' => 'contena']), $context);

        static::assertSame([[RateLimiter::LOGIN_ROUTE, $expectedCombinedKey, $tenantId]], $ensureAcceptedCalls);
        static::assertSame([
            [RateLimiter::LOGIN_USER, $expectedEmailKey, $tenantId],
            [RateLimiter::LOGIN_CLIENT, $ip, $tenantId],
        ], $ensureIfConfiguredCalls);
    }

    public function testRateLimitersAreResetOnSuccessfulLogin(): void
    {
        $email = 'user@example.com';
        $ip = '10.0.0.1';
        $combinedKey = $email . '-' . $ip;
        $tenantId = 'tenant-a';
        $context = Generator::generateChannelContext(Context::createTenantContext($tenantId));

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('reset')
            ->with(RateLimiter::LOGIN_ROUTE, $combinedKey, $context->getContext());

        $resetIfConfiguredCalls = [];
        $rateLimiter->expects($this->exactly(2))
            ->method('resetIfConfigured')
            ->willReturnCallback(function (string $route, string $key, Context $context) use (&$resetIfConfiguredCalls): void {
                $resetIfConfiguredCalls[] = [$route, $key, $context->getTenantId()];
            });

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects($this->once())->method('loginByCredentials')->willReturn('test-token');

        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => $ip]));

        $route = new LoginRoute($accountService, $requestStack, $rateLimiter);
        $route->login(new RequestDataBag(['email' => $email, 'password' => 'contena']), $context);

        static::assertSame([
            [RateLimiter::LOGIN_CLIENT, $ip, $tenantId],
            [RateLimiter::LOGIN_USER, $email, $tenantId],
        ], $resetIfConfiguredCalls);
    }

    public function testRateLimitThrowsMemberAuthThrottled(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())->method('ensureAccepted')
            ->willThrowException(new RateLimitExceededException(time() + 60));

        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => '10.0.0.1']));

        $route = new LoginRoute(
            static::createStub(AccountService::class),
            $requestStack,
            $rateLimiter,
        );

        $this->expectException(MemberException::class);

        $route->login(
            new RequestDataBag(['email' => 'test@example.com', 'password' => 'pw']),
            Generator::generateChannelContext(),
        );
    }

    public function testNoRateLimitingWithoutMainRequest(): void
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('ensureAccepted');
        $rateLimiter->expects($this->never())->method('ensureAcceptedIfConfigured');
        $rateLimiter->expects($this->never())->method('reset');
        $rateLimiter->expects($this->never())->method('resetIfConfigured');

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects($this->once())->method('loginByCredentials')->willReturn('test-token');

        $route = new LoginRoute($accountService, new RequestStack(), $rateLimiter);

        $response = $route->login(
            new RequestDataBag(['email' => 'test@example.com', 'password' => 'pw']),
            Generator::generateChannelContext(),
        );

        static::assertSame('test-token', $response->getToken());
    }
}
