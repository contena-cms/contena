<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Notification;

use Contena\Core\System\Payment\Notification\PaymentNotificationHandlerInterface;
use Contena\Core\System\Payment\Notification\PaymentNotificationHandlerRegistry;
use Contena\Core\System\Payment\Notification\PaymentNotificationTypes;
use Contena\Core\System\Payment\PaymentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PaymentNotificationHandlerRegistry::class)]
final class PaymentNotificationHandlerRegistryTest extends TestCase
{
    public function testResolvesARegisteredNotificationType(): void
    {
        $handler = static::createStub(PaymentNotificationHandlerInterface::class);
        $handler->method('getType')->willReturn(PaymentNotificationTypes::REFUND);

        static::assertSame($handler, new PaymentNotificationHandlerRegistry([$handler])->get(PaymentNotificationTypes::REFUND));
    }

    public function testDuplicateHandlersFailInsteadOfSilentlyReplacingBusinessBehavior(): void
    {
        $first = static::createStub(PaymentNotificationHandlerInterface::class);
        $first->method('getType')->willReturn(PaymentNotificationTypes::REFUND);
        $second = static::createStub(PaymentNotificationHandlerInterface::class);
        $second->method('getType')->willReturn(PaymentNotificationTypes::REFUND);

        $this->expectExceptionObject(PaymentException::invalidExtensionRegistration(PaymentNotificationHandlerInterface::class, (string) PaymentNotificationTypes::REFUND));
        new PaymentNotificationHandlerRegistry([$first, $second]);
    }

    public function testUnsupportedTypeIsRejected(): void
    {
        $this->expectExceptionObject(PaymentException::invalidRequest('The payment notification type is not supported.'));
        new PaymentNotificationHandlerRegistry([])->get(PaymentNotificationTypes::UNKNOWN);
    }
}
