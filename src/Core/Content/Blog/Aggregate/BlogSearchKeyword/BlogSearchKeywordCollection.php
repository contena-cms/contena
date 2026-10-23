<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BlogSearchKeywordEntity>
 *
 * @codeCoverageIgnore
 */
class BlogSearchKeywordCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_search_keyword_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogSearchKeywordEntity::class;
    }
}
