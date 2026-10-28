<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Aggregate\MediaThumbnailSize;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MediaThumbnailSizeEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class MediaThumbnailSizeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'media_thumbnail_size_collection';
    }

    protected function getExpectedClass(): string
    {
        return MediaThumbnailSizeEntity::class;
    }
}
