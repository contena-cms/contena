<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use CartException::unsupportedValue() or UserException::unsupportedValue() or RuleException::unsupportedValue() instead
 */
class UnsupportedValueException extends ContenaHttpException
{
    public function __construct(
        protected string $type,
        protected string $class
    ) {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            'Unsupported value of type {{ type }} in {{ class }}',
            ['type' => $type, 'class' => $class]
        );
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__RULE_VALUE_NOT_SUPPORTED';
    }
}
