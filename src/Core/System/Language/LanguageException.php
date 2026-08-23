<?php declare(strict_types=1);

namespace Contena\Core\System\Language;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class LanguageException extends HttpException
{
    final public const string VALUE_NOT_SUPPORTED = 'LANGUAGE__RULE_VALUE_NOT_SUPPORTED';
    final public const string INVALID_FIELD_VALUE_TYPE = 'LANGUAGE__INVALID_FIELD_VALUE_TYPE';

    public static function unsupportedValue(string $type, string $class): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::VALUE_NOT_SUPPORTED,
            'Unsupported value of type {{ type }} in {{ class }}',
            ['type' => $type, 'class' => $class]
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
