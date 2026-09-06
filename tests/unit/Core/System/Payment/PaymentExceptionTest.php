<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment;

use Contena\Core\System\Payment\Exception\PaymentCapabilityNotSupportedException;
use Contena\Core\System\Payment\Exception\PaymentChannelConfigNotFoundException;
use Contena\Core\System\Payment\Exception\PaymentConcurrentModificationException;
use Contena\Core\System\Payment\Exception\PaymentDuplicateReferenceException;
use Contena\Core\System\Payment\Exception\PaymentGatewayNotFoundException;
use Contena\Core\System\Payment\Exception\PaymentNotificationConfigurationMismatchException;
use Contena\Core\System\Payment\Exception\PaymentNotificationResourceNotFoundException;
use Contena\Core\System\Payment\Exception\PaymentOrderNotFoundException;
use Contena\Core\System\Payment\Exception\PaymentOrderNotSucceededException;
use Contena\Core\System\Payment\Exception\PaymentRefundAmountExceededException;
use Contena\Core\System\Payment\Exception\PaymentRefundNotFoundException;
use Contena\Core\System\Payment\Exception\PaymentRouteNotFoundException;
use Contena\Core\System\Payment\Exception\PaymentSubscriptionNotFoundException;
use Contena\Core\System\Payment\Exception\PaymentTransactionNotFoundException;
use Contena\Core\System\Payment\Exception\PaymentTransferNotFoundException;
use Contena\Core\System\Payment\PaymentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(PaymentException::class)]
#[CoversClass(PaymentTransactionNotFoundException::class)]
#[CoversClass(PaymentSubscriptionNotFoundException::class)]
#[CoversClass(PaymentTransferNotFoundException::class)]
#[CoversClass(PaymentRefundNotFoundException::class)]
#[CoversClass(PaymentCapabilityNotSupportedException::class)]
#[CoversClass(PaymentChannelConfigNotFoundException::class)]
#[CoversClass(PaymentDuplicateReferenceException::class)]
#[CoversClass(PaymentConcurrentModificationException::class)]
#[CoversClass(PaymentGatewayNotFoundException::class)]
#[CoversClass(PaymentNotificationConfigurationMismatchException::class)]
#[CoversClass(PaymentNotificationResourceNotFoundException::class)]
#[CoversClass(PaymentOrderNotFoundException::class)]
#[CoversClass(PaymentOrderNotSucceededException::class)]
#[CoversClass(PaymentRefundAmountExceededException::class)]
#[CoversClass(PaymentRouteNotFoundException::class)]
final class PaymentExceptionTest extends TestCase
{
    public function testMissingTransactionIdentifiesTheCorrectEntity(): void
    {
        $exception = PaymentException::transactionNotFound('missing-id');

        static::assertSame(PaymentTransactionNotFoundException::class, $exception::class);
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(PaymentException::TRANSACTION_NOT_FOUND, $exception->getErrorCode());
        static::assertSame(['id' => 'missing-id'], $exception->getParameters());
    }

    public function testMissingSubscriptionIdentifiesTheCorrectEntity(): void
    {
        $exception = PaymentException::subscriptionNotFound('missing-id');

        static::assertSame(PaymentSubscriptionNotFoundException::class, $exception::class);
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(PaymentException::SUBSCRIPTION_NOT_FOUND, $exception->getErrorCode());
        static::assertSame(['id' => 'missing-id'], $exception->getParameters());
    }

    public function testMissingTransferIdentifiesTheCorrectEntity(): void
    {
        $exception = PaymentException::transferNotFound('missing-id');

        static::assertSame(PaymentTransferNotFoundException::class, $exception::class);
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(PaymentException::TRANSFER_NOT_FOUND, $exception->getErrorCode());
        static::assertSame(['id' => 'missing-id'], $exception->getParameters());
    }

    public function testMissingRefundIdentifiesTheCorrectEntity(): void
    {
        $exception = PaymentException::refundNotFound('missing-id');

        static::assertSame(PaymentRefundNotFoundException::class, $exception::class);
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(PaymentException::REFUND_NOT_FOUND, $exception->getErrorCode());
        static::assertSame(['id' => 'missing-id'], $exception->getParameters());
    }

    public function testCapabilityNotSupportedHasACatchableTypeAndStableErrorContract(): void
    {
        $exception = PaymentException::capabilityNotSupported('channel', 'refund');
        $expected = new PaymentException(Response::HTTP_UNPROCESSABLE_ENTITY, PaymentException::CAPABILITY_NOT_SUPPORTED, 'Payment channel "{{ channel }}" does not support "{{ operation }}".', ['channel' => 'channel', 'operation' => 'refund']);

        static::assertSame(PaymentCapabilityNotSupportedException::class, $exception::class);
        static::assertSame($expected->getStatusCode(), $exception->getStatusCode());
        static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
        static::assertSame($expected->getMessage(), $exception->getMessage());
        static::assertSame($expected->getParameters(), $exception->getParameters());
    }

    public function testChannelConfigNotFoundHasACatchableTypeAndStableErrorContract(): void
    {
        $exception = PaymentException::channelConfigNotFound('id');
        $expected = new PaymentException(Response::HTTP_UNPROCESSABLE_ENTITY, PaymentException::CHANNEL_CONFIG_NOT_FOUND, 'Payment channel configuration "{{ id }}" was not found.', ['id' => 'id']);

        static::assertSame(PaymentChannelConfigNotFoundException::class, $exception::class);
        static::assertSame($expected->getStatusCode(), $exception->getStatusCode());
        static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
        static::assertSame($expected->getMessage(), $exception->getMessage());
        static::assertSame($expected->getParameters(), $exception->getParameters());
    }

    public function testDuplicateReferenceHasACatchableTypeAndStableErrorContract(): void
    {
        $exception = PaymentException::duplicateReference('reference');
        $expected = new PaymentException(Response::HTTP_CONFLICT, PaymentException::DUPLICATE_REFERENCE, 'Payment reference "{{ reference }}" already exists.', ['reference' => 'reference']);

        static::assertSame(PaymentDuplicateReferenceException::class, $exception::class);
        static::assertSame($expected->getStatusCode(), $exception->getStatusCode());
        static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
        static::assertSame($expected->getMessage(), $exception->getMessage());
        static::assertSame($expected->getParameters(), $exception->getParameters());
    }

    public function testConcurrentModificationHasACatchableTypeAndStableErrorContract(): void
    {
        $exception = PaymentException::concurrentModification('reference');
        $expected = new PaymentException(Response::HTTP_CONFLICT, PaymentException::CONCURRENT_MODIFICATION, 'Payment resource "{{ reference }}" changed in another transaction. Reconcile its status in a new transaction; do not repeat the financial operation.', ['reference' => 'reference']);

        static::assertSame(PaymentConcurrentModificationException::class, $exception::class);
        static::assertSame($expected->getStatusCode(), $exception->getStatusCode());
        static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
        static::assertSame($expected->getMessage(), $exception->getMessage());
        static::assertSame($expected->getParameters(), $exception->getParameters());
    }

    public function testGatewayNotFoundHasACatchableTypeAndStableErrorContract(): void
    {
        $exception = PaymentException::gatewayNotFound('code');
        $expected = new PaymentException(Response::HTTP_NOT_FOUND, PaymentException::GATEWAY_NOT_FOUND, 'Payment gateway "{{ code }}" was not found.', ['code' => 'code']);

        static::assertSame(PaymentGatewayNotFoundException::class, $exception::class);
        static::assertSame($expected->getStatusCode(), $exception->getStatusCode());
        static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
        static::assertSame($expected->getMessage(), $exception->getMessage());
        static::assertSame($expected->getParameters(), $exception->getParameters());
    }

    public function testNotificationConfigurationMismatchHasACatchableTypeAndStableErrorContract(): void
    {
        $exception = PaymentException::notificationConfigurationMismatch('reference');
        $expected = new PaymentException(Response::HTTP_UNPROCESSABLE_ENTITY, PaymentException::NOTIFICATION_CONFIGURATION_MISMATCH, 'The payment resource "{{ reference }}" does not belong to this channel configuration.', ['reference' => 'reference']);

        static::assertSame(PaymentNotificationConfigurationMismatchException::class, $exception::class);
        static::assertSame($expected->getStatusCode(), $exception->getStatusCode());
        static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
        static::assertSame($expected->getMessage(), $exception->getMessage());
        static::assertSame($expected->getParameters(), $exception->getParameters());
    }

    public function testNotificationResourceNotFoundHasACatchableTypeAndStableErrorContract(): void
    {
        $exception = PaymentException::notificationResourceNotFound('reference');
        $expected = new PaymentException(Response::HTTP_NOT_FOUND, PaymentException::NOTIFICATION_RESOURCE_NOT_FOUND, 'Payment notification resource "{{ reference }}" was not found.', ['reference' => 'reference']);

        static::assertSame(PaymentNotificationResourceNotFoundException::class, $exception::class);
        static::assertSame($expected->getStatusCode(), $exception->getStatusCode());
        static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
        static::assertSame($expected->getMessage(), $exception->getMessage());
        static::assertSame($expected->getParameters(), $exception->getParameters());
    }

    public function testOrderNotFoundHasACatchableTypeAndStableErrorContract(): void
    {
        $exception = PaymentException::orderNotFound('reference');
        $expected = new PaymentException(Response::HTTP_NOT_FOUND, PaymentException::ORDER_NOT_FOUND, 'Payment order "{{ reference }}" was not found.', ['reference' => 'reference']);

        static::assertSame(PaymentOrderNotFoundException::class, $exception::class);
        static::assertSame($expected->getStatusCode(), $exception->getStatusCode());
        static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
        static::assertSame($expected->getMessage(), $exception->getMessage());
        static::assertSame($expected->getParameters(), $exception->getParameters());
    }

    public function testOrderNotSucceededHasACatchableTypeAndStableErrorContract(): void
    {
        $exception = PaymentException::orderNotSucceeded('reference');
        $expected = new PaymentException(Response::HTTP_UNPROCESSABLE_ENTITY, PaymentException::ORDER_NOT_SUCCEEDED, 'Payment order "{{ reference }}" has not succeeded.', ['reference' => 'reference']);

        static::assertSame(PaymentOrderNotSucceededException::class, $exception::class);
        static::assertSame($expected->getStatusCode(), $exception->getStatusCode());
        static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
        static::assertSame($expected->getMessage(), $exception->getMessage());
        static::assertSame($expected->getParameters(), $exception->getParameters());
    }

    public function testRefundAmountExceededHasACatchableTypeAndStableErrorContract(): void
    {
        $exception = PaymentException::refundAmountExceeded(100);
        $expected = new PaymentException(Response::HTTP_UNPROCESSABLE_ENTITY, PaymentException::REFUND_AMOUNT_EXCEEDED, 'The refundable balance is less than {{ amount }}.', ['amount' => 100]);

        static::assertSame(PaymentRefundAmountExceededException::class, $exception::class);
        static::assertSame($expected->getStatusCode(), $exception->getStatusCode());
        static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
        static::assertSame($expected->getMessage(), $exception->getMessage());
        static::assertSame($expected->getParameters(), $exception->getParameters());
    }

    public function testRouteNotFoundHasACatchableTypeAndStableErrorContract(): void
    {
        $exception = PaymentException::routeNotFound('app', null);
        $expected = new PaymentException(Response::HTTP_UNPROCESSABLE_ENTITY, PaymentException::ROUTE_NOT_FOUND, 'No payment route is available for app "{{ appId }}" and method "{{ method }}".', ['appId' => 'app', 'method' => '-']);

        static::assertSame(PaymentRouteNotFoundException::class, $exception::class);
        static::assertSame($expected->getStatusCode(), $exception->getStatusCode());
        static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
        static::assertSame($expected->getMessage(), $exception->getMessage());
        static::assertSame($expected->getParameters(), $exception->getParameters());
    }
}
