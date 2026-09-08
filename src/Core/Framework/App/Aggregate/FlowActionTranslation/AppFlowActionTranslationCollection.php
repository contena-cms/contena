<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\FlowActionTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AppFlowActionTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class AppFlowActionTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppFlowActionTranslationEntity::class;
    }
}
