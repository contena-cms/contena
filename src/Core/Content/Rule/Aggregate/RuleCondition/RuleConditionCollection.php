<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule\Aggregate\RuleCondition;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<RuleConditionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class RuleConditionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'rule_condition_collection';
    }

    protected function getExpectedClass(): string
    {
        return RuleConditionEntity::class;
    }
}
