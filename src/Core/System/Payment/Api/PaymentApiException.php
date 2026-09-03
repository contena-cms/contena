<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Api;

use Contena\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

class PaymentApiException extends PaymentException
{
    final public const string INVALID_SIGNATURE = 'PAYMENT_API__INVALID_SIGNATURE';
    final public const string MISSING_PARAMETER = 'PAYMENT_API__MISSING_PARAMETER';
    final public const string SCHEMA_UNAVAILABLE = 'PAYMENT_API__SCHEMA_UNAVAILABLE';

    public static function invalidSignature(): self
    {
        return new self(Response::HTTP_UNAUTHORIZED, self::INVALID_SIGNATURE, 'Payment request signature verification failed.');
    }

    public static function missingParameter(string $parameter): self
    {
        return new self(Response::HTTP_BAD_REQUEST, self::MISSING_PARAMETER, 'Request parameter "{{ parameter }}" is required.', ['parameter' => $parameter]);
    }

    public static function schemaUnavailable(): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, self::SCHEMA_UNAVAILABLE, 'The Payment API schema could not be loaded.');
    }

    public static function invalidRequest(string $message): self
    {
        return new self(Response::HTTP_BAD_REQUEST, PaymentException::INVALID_REQUEST, $message);
    }
}
