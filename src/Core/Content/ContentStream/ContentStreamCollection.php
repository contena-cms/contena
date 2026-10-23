<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ContentStreamEntity>
 *
 * @codeCoverageIgnore
 */
class ContentStreamCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'content_stream_collection';
    }

    protected function getExpectedClass(): string
    {
        return ContentStreamEntity::class;
    }
}
