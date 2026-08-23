<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Event;

use Symfony\Contracts\EventDispatcher\Event;

class BeforeSystemConfigMultipleChangedEvent extends Event
{
    /**
     * @param array<string, array<mixed>|bool|float|int|string|null> $config
     */
    public function __construct(
        private array $config,
        private readonly ?string $channelId,
    ) {
    }

    /**
     * @return array<string, array<mixed>|bool|float|int|string|null>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<mixed>|bool|float|int|string|null $value
     */
    public function setValue(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    public function getChannelId(): ?string
    {
        return $this->channelId;
    }
}
