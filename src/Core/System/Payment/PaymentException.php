<?php declare(strict_types=1);

namespace Contena\Core\System\Payment;

use Contena\Core\Framework\HttpException;
use Contena\Core\System\Payment\Exception\PaymentAppNotFoundException;
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
use Symfony\Component\HttpFoundation\Response;

class PaymentException extends HttpException
{
    final public const string APP_NOT_FOUND = 'PAYMENT__APP_NOT_FOUND';
    final public const string CAPABILITY_NOT_SUPPORTED = 'PAYMENT__CAPABILITY_NOT_SUPPORTED';
    final public const string CHANNEL_CONFIG_NOT_FOUND = 'PAYMENT__CHANNEL_CONFIG_NOT_FOUND';
    final public const string CONCURRENT_MODIFICATION = 'PAYMENT__CONCURRENT_MODIFICATION';
    final public const string DUPLICATE_REFERENCE = 'PAYMENT__DUPLICATE_REFERENCE';
    final public const string GATEWAY_NOT_FOUND = 'PAYMENT__GATEWAY_NOT_FOUND';
    final public const string INVALID_EXTENSION_REGISTRATION = 'PAYMENT__INVALID_EXTENSION_REGISTRATION';
    final public const string INVALID_REQUEST = 'PAYMENT__INVALID_REQUEST';
    final public const string NOTIFICATION_CONFIGURATION_MISMATCH = 'PAYMENT__NOTIFICATION_CONFIGURATION_MISMATCH';
    final public const string NOTIFICATION_RESOURCE_NOT_FOUND = 'PAYMENT__NOTIFICATION_RESOURCE_NOT_FOUND';
    final public const string ORDER_NOT_FOUND = 'PAYMENT__ORDER_NOT_FOUND';
    final public const string ORDER_NOT_SUCCEEDED = 'PAYMENT__ORDER_NOT_SUCCEEDED';
    final public const string REFUND_AMOUNT_EXCEEDED = 'PAYMENT__REFUND_AMOUNT_EXCEEDED';
    final public const string REFUND_NOT_FOUND = 'PAYMENT__REFUND_NOT_FOUND';
    final public const string ROUTE_NOT_FOUND = 'PAYMENT__ROUTE_NOT_FOUND';
    final public const string SUBSCRIPTION_NOT_FOUND = 'PAYMENT__SUBSCRIPTION_NOT_FOUND';
    final public const string TRANSACTION_NOT_FOUND = 'PAYMENT__TRANSACTION_NOT_FOUND';
    final public const string TRANSFER_NOT_FOUND = 'PAYMENT__TRANSFER_NOT_FOUND';

    public static function appNotFound(string $code): PaymentAppNotFoundException
    {
        return new PaymentAppNotFoundException($code);
    }

    public static function capabilityNotSupported(string $channel, string $operation): PaymentCapabilityNotSupportedException
    {
        return new PaymentCapabilityNotSupportedException($channel, $operation);
    }

    public static function channelConfigNotFound(string $id): PaymentChannelConfigNotFoundException
    {
        return new PaymentChannelConfigNotFoundException($id);
    }

    public static function concurrentModification(string $reference): PaymentConcurrentModificationException
    {
        return new PaymentConcurrentModificationException($reference);
    }

    public static function duplicateReference(string $reference): PaymentDuplicateReferenceException
    {
        return new PaymentDuplicateReferenceException($reference);
    }

    public static function gatewayNotFound(string $code): PaymentGatewayNotFoundException
    {
        return new PaymentGatewayNotFoundException($code);
    }

    public static function invalidExtensionRegistration(string $contract, string $key): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, self::INVALID_EXTENSION_REGISTRATION, 'Payment extension "{{ contract }}" requires a non-empty, unique key; received "{{ key }}".', ['contract' => $contract, 'key' => $key]);
    }

    public static function invalidRequest(string $message): self
    {
        return new self(Response::HTTP_BAD_REQUEST, self::INVALID_REQUEST, $message);
    }

    public static function notificationConfigurationMismatch(string $reference): PaymentNotificationConfigurationMismatchException
    {
        return new PaymentNotificationConfigurationMismatchException($reference);
    }

    public static function notificationResourceNotFound(string $reference): PaymentNotificationResourceNotFoundException
    {
        return new PaymentNotificationResourceNotFoundException($reference);
    }

    public static function orderNotFound(string $reference): PaymentOrderNotFoundException
    {
        return new PaymentOrderNotFoundException($reference);
    }

    public static function orderNotSucceeded(string $reference): PaymentOrderNotSucceededException
    {
        return new PaymentOrderNotSucceededException($reference);
    }

    public static function refundAmountExceeded(int $amount): PaymentRefundAmountExceededException
    {
        return new PaymentRefundAmountExceededException($amount);
    }

    public static function refundNotFound(string $id): PaymentRefundNotFoundException
    {
        return new PaymentRefundNotFoundException($id);
    }

    public static function routeNotFound(string $appId, ?string $method = null): PaymentRouteNotFoundException
    {
        return new PaymentRouteNotFoundException($appId, $method);
    }

    public static function subscriptionNotFound(string $id): PaymentSubscriptionNotFoundException
    {
        return new PaymentSubscriptionNotFoundException($id);
    }

    public static function transactionNotFound(string $id): PaymentTransactionNotFoundException
    {
        return new PaymentTransactionNotFoundException($id);
    }

    public static function transferNotFound(string $id): PaymentTransferNotFoundException
    {
        return new PaymentTransferNotFoundException($id);
    }
}
