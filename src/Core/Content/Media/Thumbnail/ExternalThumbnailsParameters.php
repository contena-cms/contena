<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Thumbnail;

/**
 * @final
 */
class ExternalThumbnailsParameters
{
    public function __construct(
        public readonly ExternalThumbnailCollection $thumbnails = new ExternalThumbnailCollection(),
    ) {
    }
}
