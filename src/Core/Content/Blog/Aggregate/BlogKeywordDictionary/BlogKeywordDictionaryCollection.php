<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogKeywordDictionary;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BlogKeywordDictionaryEntity>
 */
class BlogKeywordDictionaryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_keyword_dictionary_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogKeywordDictionaryEntity::class;
    }
}
