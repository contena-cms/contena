<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelType;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\System\Channel\ChannelCollection;

/**
 * @extends EntityCollection<ChannelTypeEntity>
 */
class ChannelTypeCollection extends EntityCollection
{
    public function getChannels(): ChannelCollection
    {
        return new ChannelCollection(
            $this->flatMap(static fn (ChannelTypeEntity $channel) => $channel->getChannels())
        );
    }

    public function getApiAlias(): string
    {
        return 'channel_type_collection';
    }

    protected function getExpectedClass(): string
    {
        return ChannelTypeEntity::class;
    }
}
