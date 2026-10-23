<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule\Exception;

use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
class InvalidConditionException extends ContenaHttpException
{
    public function __construct(string $conditionName)
    {
        parent::__construct('The condition "{{ condition }}" is invalid.', ['condition' => $conditionName]);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_CONDITION_ERROR';
    }
}
