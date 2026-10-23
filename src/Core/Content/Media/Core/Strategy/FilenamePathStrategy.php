<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Core\Strategy;

use Contena\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Contena\Core\Content\Media\Core\Params\MediaLocationStruct;
use Contena\Core\Content\Media\Core\Params\ThumbnailLocationStruct;

/**
 * @internal Concrete implementation is not allowed to be decorated or extended. The implementation details can change
 */
class FilenamePathStrategy extends AbstractMediaPathStrategy
{
    public function name(): string
    {
        return 'filename';
    }

    protected function value(MediaLocationStruct|ThumbnailLocationStruct $location): ?string
    {
        return $location instanceof ThumbnailLocationStruct ? $location->media->fileName : $location->fileName;
    }
}
