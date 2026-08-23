<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(UserException::class)]
class UserExceptionTest extends TestCase
{
    public function testChannelNotFound(): void
    {
        $exception = UserException::channelNotFound();

        static::assertInstanceOf(UserException::class, $exception);
        static::assertSame(Response::HTTP_PRECONDITION_FAILED, $exception->getStatusCode());
        static::assertSame(UserException::CHANNEL_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('No channel found.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }
}
