<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Sorting;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BlogSortingTranslationEntity>
 */
class BlogSortingTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_sorting_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogSortingTranslationEntity::class;
    }
}
