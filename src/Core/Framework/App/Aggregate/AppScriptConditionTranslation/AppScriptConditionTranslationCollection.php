<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppScriptConditionTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AppScriptConditionTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class AppScriptConditionTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'app_script_condition_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return AppScriptConditionTranslationEntity::class;
    }
}
