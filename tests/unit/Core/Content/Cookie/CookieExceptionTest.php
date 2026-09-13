<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cookie;

use Contena\Core\Content\Cookie\CookieException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CookieException::class)]
class CookieExceptionTest extends TestCase
{
    public function testInvalidConsentLogPayload(): void
    {
        $exception = CookieException::invalidConsentLogPayload('body must be a JSON object');

        static::assertSame('CONTENT__COOKIE_INVALID_CONSENT_LOG_PAYLOAD', $exception->getErrorCode());
        static::assertSame('Invalid cookie consent log payload: body must be a JSON object', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testConsentLogStorageNotFound(): void
    {
        $exception = CookieException::consentLogStorageNotFound('s3', ['database', 'none']);

        static::assertSame('CONTENT__COOKIE_CONSENT_LOG_STORAGE_NOT_FOUND', $exception->getErrorCode());
        static::assertSame('The cookie consent log storage "s3" is not available. Available storages are: "database", "none".', $exception->getMessage());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testInvalidConsentId(): void
    {
        $exception = CookieException::invalidConsentId('../etc/passwd');

        static::assertSame('CONTENT__COOKIE_INVALID_CONSENT_ID', $exception->getErrorCode());
        static::assertSame('The cookie consent id "../etc/passwd" does not match the pattern /^[A-Za-z0-9_-]{1,64}$/', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }
}
