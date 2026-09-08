<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<AppTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class AppTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppTranslationEntity::class;
    }
}
