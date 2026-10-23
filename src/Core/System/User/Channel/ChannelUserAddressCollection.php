<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class ChannelUserAddressCollection extends UserAddressCollection
{
    public function getApiAlias(): string
    {
        return 'channel_customer_address_collection';
    }

    protected function getExpectedClass(): string
    {
        return ChannelUserAddressEntity::class;
    }
}
