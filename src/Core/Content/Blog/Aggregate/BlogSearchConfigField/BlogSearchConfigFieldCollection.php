<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogSearchConfigField;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BlogSearchConfigFieldEntity>
 */
class BlogSearchConfigFieldCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_search_config_field_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogSearchConfigFieldEntity::class;
    }
}
