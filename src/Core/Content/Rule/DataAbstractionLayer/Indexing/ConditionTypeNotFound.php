<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule\DataAbstractionLayer\Indexing;

use Contena\Core\Content\Rule\RuleException;
use Symfony\Component\HttpFoundation\Response;

class ConditionTypeNotFound extends RuleException
{
    public function __construct(string $conditionName)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'CONTENT__RULE_CONDITION_TYPE_NOT_FOUND',
            'Rule condition type "{{ conditionName }}" was not found.',
            ['conditionName' => $conditionName]
        );
    }
}
