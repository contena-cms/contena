<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppScriptCondition;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AppScriptConditionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppScriptConditionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'app_script_condition_collection';
    }

    protected function getExpectedClass(): string
    {
        return AppScriptConditionEntity::class;
    }
}
