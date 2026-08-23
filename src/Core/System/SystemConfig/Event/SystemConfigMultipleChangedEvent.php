<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Event;

use Symfony\Contracts\EventDispatcher\Event;

class SystemConfigMultipleChangedEvent extends Event
{
    /**
     * @param array<string, array<mixed>|bool|float|int|string|null> $config
     */
    public function __construct(
        private readonly array $config,
        private readonly ?string $channelId,
        private readonly bool $silent = true,
    ) {
    }

    /**
     * @return array<string, array<mixed>|bool|float|int|string|null>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function isSilent(): bool
    {
        return $this->silent;
    }

    public function getChannelId(): ?string
    {
        return $this->channelId;
    }
}
