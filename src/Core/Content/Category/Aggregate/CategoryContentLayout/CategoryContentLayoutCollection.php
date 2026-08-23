<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Aggregate\CategoryContentLayout;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<CategoryContentLayoutEntity>
 */
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
