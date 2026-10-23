<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\AdminUser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\AdminUser\AdminUserException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AdminUserException::class)]
class AdminUserExceptionTest extends TestCase
{
    public function testChannelNotFound(): void
    {
        $exception = AdminUserException::channelNotFound();

        static::assertInstanceOf(AdminUserException::class, $exception);
        static::assertSame(Response::HTTP_PRECONDITION_FAILED, $exception->getStatusCode());
        static::assertSame(AdminUserException::CHANNEL_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('No sales channel found.', $exception->getMessage());
        static::assertCount(0, $exception->getParameters());
    }

    public function testMissingRequestParameter(): void
    {
        $exception = AdminUserException::missingRequestParameter('email', 'admin_user.email');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(AdminUserException::MISSING_REQUEST_PARAMETER_CODE, $exception->getErrorCode());
        static::assertSame('Parameter "email" is missing.', $exception->getMessage());
        static::assertSame([
            'parameterName' => 'email',
            'path' => 'admin_user.email',
        ], $exception->getParameters());
    }
}
