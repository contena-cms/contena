<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;

abstract class AbstractChannelContextFactory
{
    abstract public function getDecorated(): AbstractChannelContextFactory;

    /**
     * @param array<string, string|array<string,bool>|UserAddressEntity|null> $options
     */
    abstract public function create(string $token, string $channelId, array $options = []): ChannelContext;
}
