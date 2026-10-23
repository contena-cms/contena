<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Context;


class ChannelApiSource implements ContextSource, \JsonSerializable
{
    use JsonSerializableTrait;
use Contena\Core\Framework\Struct\JsonSerializableTrait;

    public string $type = 'sales-channel';

    public function __construct(private readonly string $channelId)
    {
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }
}
