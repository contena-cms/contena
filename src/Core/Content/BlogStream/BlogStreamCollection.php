<?php declare(strict_types=1);

namespace Contena\Core\Content\BlogStream;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BlogStreamEntity>
 *
 * @codeCoverageIgnore
 */
class BlogStreamCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_stream_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogStreamEntity::class;
    }
}
