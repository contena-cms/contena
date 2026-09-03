<?php declare(strict_types=1);

namespace Contena\Core\System\Payment;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class PaymentException extends HttpException
{
    final public const string APP_NOT_FOUND = 'PAYMENT__APP_NOT_FOUND';
    final public const string CAPABILITY_NOT_SUPPORTED = 'PAYMENT__CAPABILITY_NOT_SUPPORTED';
    final public const string CHANNEL_CONFIG_INVALID = 'PAYMENT__CHANNEL_CONFIG_INVALID';
    final public const string CHANNEL_CONFIG_NOT_FOUND = 'PAYMENT__CHANNEL_CONFIG_NOT_FOUND';
    final public const string DUPLICATE_REFERENCE = 'PAYMENT__DUPLICATE_REFERENCE';
    final public const string GATEWAY_NOT_FOUND = 'PAYMENT__GATEWAY_NOT_FOUND';
    final public const string INVALID_REQUEST = 'PAYMENT__INVALID_REQUEST';
    final public const string ORDER_NOT_FOUND = 'PAYMENT__ORDER_NOT_FOUND';
    final public const string ORDER_NOT_SUCCEEDED = 'PAYMENT__ORDER_NOT_SUCCEEDED';
    final public const string REFUND_AMOUNT_EXCEEDED = 'PAYMENT__REFUND_AMOUNT_EXCEEDED';
    final public const string ROUTE_NOT_FOUND = 'PAYMENT__ROUTE_NOT_FOUND';

    public static function appNotFound(string $code): self
    {
        return new self(Response::HTTP_UNAUTHORIZED, self::APP_NOT_FOUND, 'Payment app "{{ code }}" was not found or is disabled.', ['code' => $code]);
    }

    public static function capabilityNotSupported(string $channel, string $operation): self
    {
        return new self(Response::HTTP_UNPROCESSABLE_ENTITY, self::CAPABILITY_NOT_SUPPORTED, 'Payment channel "{{ channel }}" does not support "{{ operation }}".', ['channel' => $channel, 'operation' => $operation]);
    }

    /**
     * @param list<string> $fields
     */
    public static function invalidChannelConfig(string $channel, array $fields): self
    {
        return new self(Response::HTTP_UNPROCESSABLE_ENTITY, self::CHANNEL_CONFIG_INVALID, 'Payment channel "{{ channel }}" has invalid configuration fields: {{ fields }}.', ['channel' => $channel, 'fields' => implode(', ', $fields)]);
    }

    public static function channelConfigNotFound(string $id): self
    {
        return new self(Response::HTTP_UNPROCESSABLE_ENTITY, self::CHANNEL_CONFIG_NOT_FOUND, 'Payment channel configuration "{{ id }}" was not found.', ['id' => $id]);
    }

    public static function duplicateReference(string $reference): self
    {
        return new self(Response::HTTP_CONFLICT, self::DUPLICATE_REFERENCE, 'Payment reference "{{ reference }}" already exists.', ['reference' => $reference]);
    }

    public static function gatewayNotFound(string $code): self
    {
        return new self(Response::HTTP_NOT_FOUND, self::GATEWAY_NOT_FOUND, 'Payment gateway "{{ code }}" was not found.', ['code' => $code]);
    }

    public static function invalidRequest(string $message): self
    {
        return new self(Response::HTTP_BAD_REQUEST, self::INVALID_REQUEST, $message);
    }

    public static function orderNotFound(string $reference): self
    {
        return new self(Response::HTTP_NOT_FOUND, self::ORDER_NOT_FOUND, 'Payment order "{{ reference }}" was not found.', ['reference' => $reference]);
    }

    public static function orderNotSucceeded(string $reference): self
    {
        return new self(Response::HTTP_UNPROCESSABLE_ENTITY, self::ORDER_NOT_SUCCEEDED, 'Payment order "{{ reference }}" has not succeeded.', ['reference' => $reference]);
    }

    public static function refundAmountExceeded(int $amount): self
    {
        return new self(Response::HTTP_UNPROCESSABLE_ENTITY, self::REFUND_AMOUNT_EXCEEDED, 'The refundable balance is less than {{ amount }}.', ['amount' => $amount]);
    }

    public static function routeNotFound(string $appId, string $operation, ?string $method = null): self
    {
        return new self(Response::HTTP_UNPROCESSABLE_ENTITY, self::ROUTE_NOT_FOUND, 'No payment route is available for app "{{ appId }}", operation "{{ operation }}" and method "{{ method }}".', ['appId' => $appId, 'operation' => $operation, 'method' => $method ?? '-']);
    }
}
