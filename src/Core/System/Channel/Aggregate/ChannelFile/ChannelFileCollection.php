<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelFile;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ChannelFileEntity>
 */
class ChannelFileCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'channel_file_collection';
    }

    protected function getExpectedClass(): string
    {
        return ChannelFileEntity::class;
    }
}
