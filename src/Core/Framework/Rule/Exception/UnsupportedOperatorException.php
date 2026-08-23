<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class UnsupportedOperatorException extends ContenaHttpException
{
    public function __construct(
        protected string $operator,
        protected string $class,
    ) {
        parent::__construct(
            'Unsupported operator {{ operator }} in {{ class }}',
            ['operator' => $this->operator, 'class' => $this->class]
        );
    }

    public function getOperator(): string
    {
        return $this->operator;
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
        return 'CONTENT__RULE_OPERATOR_NOT_SUPPORTED';
    }
}
