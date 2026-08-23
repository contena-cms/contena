<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogContentLayout;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<BlogContentLayoutEntity>
 */
class BlogContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogContentLayoutEntity::class;
    }
}
