<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogSearchConfig;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BlogSearchConfigEntity>
 */
class BlogSearchConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_search_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogSearchConfigEntity::class;
    }
}
