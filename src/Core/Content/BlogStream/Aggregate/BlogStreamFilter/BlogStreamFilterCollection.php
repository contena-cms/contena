<?php declare(strict_types=1);

namespace Contena\Core\Content\BlogStream\Aggregate\BlogStreamFilter;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BlogStreamFilterEntity>
 *
 * @codeCoverageIgnore
 */
class BlogStreamFilterCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_stream_filter_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogStreamFilterEntity::class;
    }
}
