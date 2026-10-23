<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\CmsBlock;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @extends EntityCollection<AppCmsBlockEntity>
 *
 * @codeCoverageIgnore
 */
class AppCmsBlockCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppCmsBlockEntity::class;
    }
}
