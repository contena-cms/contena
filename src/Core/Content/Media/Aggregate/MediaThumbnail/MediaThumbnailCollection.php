<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Aggregate\MediaThumbnail;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MediaThumbnailEntity>
 *
 * @codeCoverageIgnore
 */
class MediaThumbnailCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'media_thumbnail_collection';
    }

    protected function getExpectedClass(): string
    {
        return MediaThumbnailEntity::class;
    }
}
