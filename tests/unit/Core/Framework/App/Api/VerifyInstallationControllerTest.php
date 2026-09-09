<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Api;

use Contena\Core\Framework\App\Api\DTO\VerifyInstallation;
use Contena\Core\Framework\App\Api\VerifyInstallationController;
use Contena\Core\Framework\App\Url\AppUrlVerifier;
use Contena\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(VerifyInstallationController::class)]
class VerifyInstallationControllerTest extends TestCase
{
    private VerifyInstallationController $controller;

    private AppUrlVerifier&Stub $appUrlVerifier;

    private Stub&RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->appUrlVerifier = static::createStub(AppUrlVerifier::class);
        $this->rateLimiter = static::createStub(RateLimiter::class);
        $this->controller = new VerifyInstallationController($this->rateLimiter, $this->appUrlVerifier);
    }

    public function testRateLimiter(): void
    {
        $e = new RateLimitExceededException(time());
        static::expectExceptionObject($e);

        $rateLimiter = static::createMock(RateLimiter::class);
        $rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::APP_INSTALLATION_VERIFY, '127.0.0.1')
            ->willThrowException($e);
        $controller = new VerifyInstallationController($rateLimiter, $this->appUrlVerifier);

        $request = new Request(
            server: ['REMOTE_ADDR' => '127.0.0.1']
        );

        $controller->verify(new VerifyInstallation('some-run-id', 'some-token'), $request);
    }

    #[DataProvider('requestProvider')]
    public function testControllerErrorConditions(VerifyInstallation $verifyInstallationRequest, Request $request, int $expectedResponseCode): void
    {
        $response = $this->controller->verify($verifyInstallationRequest, $request);

        static::assertSame($expectedResponseCode, $response->getStatusCode());
    }

    public static function requestProvider(): \Generator
    {
        yield 'no-ip-present' => [
            new VerifyInstallation('some-run-id', 'some-token'),
            new Request(),
            Response::HTTP_BAD_REQUEST,
        ];
    }

    public function testVerificationFailReturnsBadRequest(): void
    {
        $appUrlVerifier = static::createMock(AppUrlVerifier::class);
        $appUrlVerifier->expects($this->once())
            ->method('completeVerification')
            ->with('some-run-id', 'some-token')
            ->willReturn(false);
        $controller = new VerifyInstallationController($this->rateLimiter, $appUrlVerifier);

        $request = new Request(
            query: ['runId' => 'some-run-id', 'token' => 'some-token'],
            server: ['REMOTE_ADDR' => '127.0.0.1']
        );

        $response = $controller->verify(new VerifyInstallation('some-run-id', 'some-token'), $request);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testVerificationPassReturnsNoContent(): void
    {
        $appUrlVerifier = static::createMock(AppUrlVerifier::class);
        $appUrlVerifier->expects($this->once())
            ->method('completeVerification')
            ->with('some-run-id', 'some-token')
            ->willReturn(true);
        $controller = new VerifyInstallationController($this->rateLimiter, $appUrlVerifier);

        $request = new Request(
            query: ['runId' => 'some-run-id', 'token' => 'some-token'],
            server: ['REMOTE_ADDR' => '127.0.0.1']
        );

        $response = $controller->verify(new VerifyInstallation('some-run-id', 'some-token'), $request);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
