<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\CmsBlockTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @extends EntityCollection<AppCmsBlockTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class AppCmsBlockTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppCmsBlockTranslationEntity::class;
    }
}
