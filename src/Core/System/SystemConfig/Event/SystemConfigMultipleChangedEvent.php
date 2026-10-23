<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
class SystemConfigMultipleChangedEvent extends Event
{
    /**
     * @param array<string, array<mixed>|bool|float|int|string|null> $config
     */
    public function __construct(
        private readonly array $config,
        private readonly ?string $channelId
    ) {
    }

    /**
     * @return array<string, array<mixed>|bool|float|int|string|null>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function getChannelId(): ?string
    {
        return $this->channelId;
    }
}
