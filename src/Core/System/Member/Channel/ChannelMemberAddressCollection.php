<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressCollection;

class ChannelMemberAddressCollection extends MemberAddressCollection
{
    public function getApiAlias(): string
    {
        return 'channel_member_address_collection';
    }

    protected function getExpectedClass(): string
    {
        return ChannelMemberAddressEntity::class;
    }
}
