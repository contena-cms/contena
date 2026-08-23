<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Core\Strategy;

use Contena\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Contena\Core\Content\Media\Core\Params\MediaLocationStruct;
use Contena\Core\Content\Media\Core\Params\ThumbnailLocationStruct;

/**
 * @internal Concrete implementation is not allowed to be decorated or extended. The implementation details can change
 */
class PhysicalFilenamePathStrategy extends AbstractMediaPathStrategy
{
    public function name(): string
    {
        return 'physical_filename';
    }

    protected function value(MediaLocationStruct|ThumbnailLocationStruct $location): ?string
    {
        $media = $location instanceof ThumbnailLocationStruct ? $location->media : $location;

        $timestamp = $media->uploadedAt ? $media->uploadedAt->getTimestamp() . '/' : '';

        return $timestamp . $media->fileName;
    }
}
