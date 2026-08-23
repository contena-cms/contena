<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule;

use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\Rule\Exception\InvalidConditionException;
use Symfony\Component\HttpFoundation\Response;

class RuleException extends HttpException
{
    final public const string RULE_OPERATOR_NOT_SUPPORTED = 'FRAMEWORK__RULE_OPERATOR_NOT_SUPPORTED';
    final public const string VALUE_NOT_SUPPORTED = 'CONTENT__RULE_VALUE_NOT_SUPPORTED';
    final public const string MULTIPLE_NOT_RULES = 'CONTENT__TOO_MANY_NOT_RULES';
    final public const string INVALID_DATE_RANGE_USAGE = 'FRAMEWORK__INVALID_DATE_RANGE_USAGE';
    final public const string RULE_NAME_NOT_IMPLEMENTED = 'FRAMEWORK__RULE_NAME_NOT_IMPLEMENTED';

    public static function unsupportedOperator(string $operator, string $class): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::RULE_OPERATOR_NOT_SUPPORTED,
            'Unsupported operator {{ operator }} in {{ class }}',
            ['operator' => $operator, 'class' => $class],
        );
    }

    public static function unsupportedValue(string $type, string $class): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::VALUE_NOT_SUPPORTED,
            'Unsupported value of type {{ type }} in {{ class }}',
            ['type' => $type, 'class' => $class],
        );
    }

    public static function onlyOneNotRule(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MULTIPLE_NOT_RULES,
            'NOT rule can only hold one rule',
        );
    }

    public static function invalidDateRangeUsage(string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_DATE_RANGE_USAGE,
            'Invalid date range usage: {{ reason }}',
            ['reason' => $reason],
        );
    }

    public static function ruleNameNotImplemented(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::RULE_NAME_NOT_IMPLEMENTED,
            'Implement own getName or add RULE_NAME constant',
        );
    }

    public static function invalidCondition(string $conditionName): InvalidConditionException
    {
        return new InvalidConditionException($conditionName);
    }
}
