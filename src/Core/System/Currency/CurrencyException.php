<?php declare(strict_types=1);

namespace Contena\Core\System\Currency;

use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('fundamentals@framework')]
class CurrencyException extends HttpException
{
    final public const ISO_CODE_NOT_UNIQUE = 'SYSTEM__CURRENCY_ISO_CODE_NOT_UNIQUE';
    final public const INVALID_FIELD_VALUE_TYPE = 'SYSTEM__CURRENCY_INVALID_FIELD_VALUE_TYPE';

    public static function isoCodeNotUnique(string $isoCode, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ISO_CODE_NOT_UNIQUE,
            'The ISO code "{{ isoCode }}" is already in use.',
            ['isoCode' => $isoCode],
            $e,
        );
    }

    public static function invalidFieldValueType(string $fieldName, string $expectedType, string $actualType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_FIELD_VALUE_TYPE,
            'Field {{ fieldName }} expected {{ expectedType }}, got {{ actualType }}',
            ['fieldName' => $fieldName, 'expectedType' => $expectedType, 'actualType' => $actualType]
        );
    }

}
