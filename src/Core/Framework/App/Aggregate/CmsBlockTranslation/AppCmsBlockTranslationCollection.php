<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\CmsBlockTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<AppCmsBlockTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class AppCmsBlockTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppCmsBlockTranslationEntity::class;
    }
}
