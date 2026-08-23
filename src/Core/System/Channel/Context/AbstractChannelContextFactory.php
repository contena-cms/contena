<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\System\Channel\ChannelContext;

abstract class AbstractChannelContextFactory
{
    abstract public function getDecorated(): AbstractChannelContextFactory;

    /**
     * @param array<string, string|array<string, bool>|null> $options
     */
    abstract public function create(string $token, string $channelId, array $options = []): ChannelContext;
}
