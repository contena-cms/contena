<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Aggregate\MediaFolderConfiguration;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MediaFolderConfigurationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class MediaFolderConfigurationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'media_folder_configuration_collection';
    }

    protected function getExpectedClass(): string
    {
        return MediaFolderConfigurationEntity::class;
    }
}
