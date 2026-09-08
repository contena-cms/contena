<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Notification;

use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Notification\NotificationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NotificationException::class)]
class NotificationExceptionTest extends TestCase
{
    public function testAdminApiSourceExpected(): void
    {
        $exception = NotificationException::invalidAdminSource(SystemSource::class);

        static::assertSame(InvalidContextSourceException::class, $exception::class);
        static::assertSame(ApiException::API_INVALID_CONTEXT_SOURCE, $exception->getErrorCode());
    }
}
