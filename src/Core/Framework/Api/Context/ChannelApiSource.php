<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Context;

use Contena\Core\Framework\Struct\JsonSerializableTrait;

class ChannelApiSource implements ContextSource, \JsonSerializable
{
    use JsonSerializableTrait;

    public string $type = 'channel';

    public function __construct(private readonly string $channelId)
    {
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }
}
