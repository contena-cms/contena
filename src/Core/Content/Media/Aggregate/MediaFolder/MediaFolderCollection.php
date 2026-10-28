<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Aggregate\MediaFolder;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MediaFolderEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class MediaFolderCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'media_folder_collection';
    }

    protected function getExpectedClass(): string
    {
        return MediaFolderEntity::class;
    }
}
