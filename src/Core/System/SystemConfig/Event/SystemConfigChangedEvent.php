<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Event;

use Symfony\Contracts\EventDispatcher\Event;

class SystemConfigChangedEvent extends Event
{
    /**
     * @internal
     *
     * @param array<string, mixed>|bool|float|int|string|null $value
     */
    public function __construct(
        private readonly string $key,
        private readonly array|bool|float|int|string|null $value,
        private readonly ?string $channelId,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return array<string, mixed>|bool|float|int|string|null
     */
    public function getValue(): array|bool|float|int|string|null
    {
        return $this->value;
    }

    public function getChannelId(): ?string
    {
        return $this->channelId;
    }
}
