<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Aggregate\CategoryContentLayout;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<CategoryContentLayoutEntity>
 */
#[Package('discovery')]
class CategoryContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'category_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return CategoryContentLayoutEntity::class;
    }
}
