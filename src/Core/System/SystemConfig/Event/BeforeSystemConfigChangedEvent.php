<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Event;

use Symfony\Contracts\EventDispatcher\Event;

class BeforeSystemConfigChangedEvent extends Event
{
    /**
     * @param array<string, mixed>|bool|float|int|string|null $value
     */
    public function __construct(
        private readonly string $key,
        private array|bool|float|int|string|null $value,
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

    /**
     * @param array<string, mixed>|bool|float|int|string|null $value
     */
    public function setValue(array|bool|float|int|string|null $value): void
    {
        $this->value = $value;
    }

    public function getChannelId(): ?string
    {
        return $this->channelId;
    }
}
