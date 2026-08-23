<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Thumbnail;

use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ExternalThumbnailData>
 */
class ExternalThumbnailCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return ExternalThumbnailData::class;
    }
}
