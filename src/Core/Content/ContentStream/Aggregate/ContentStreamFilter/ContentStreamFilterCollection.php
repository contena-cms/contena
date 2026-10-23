<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream\Aggregate\ContentStreamFilter;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ContentStreamFilterEntity>
 *
 * @codeCoverageIgnore
 */
class ContentStreamFilterCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'content_stream_filter_collection';
    }

    protected function getExpectedClass(): string
    {
        return ContentStreamFilterEntity::class;
    }
}
