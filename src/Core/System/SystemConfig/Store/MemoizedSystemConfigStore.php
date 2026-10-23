<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Store;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
final class MemoizedSystemConfigStore implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<string, array<mixed>>
     */
    private array $configs = [];

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => [
                ['onValueChanged', 1500],
            ],
        ];
    }

    public function onValueChanged(SystemConfigChangedEvent $event): void
    {
        $this->removeConfig($event->getChannelId());
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(?string $channelId, array $config): void
    {
        $this->configs[$this->getKey($channelId)] = $config;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function getConfig(?string $channelId): ?array
    {
        return $this->configs[$this->getKey($channelId)] ?? null;
    }

    public function removeConfig(?string $channelId): void
    {
        if ($channelId === null) {
            $this->reset();

            return;
        }

        unset($this->configs[$this->getKey($channelId)]);
    }

    public function reset(): void
    {
        $this->configs = [];
    }

    private function getKey(?string $channelId): string
    {
        return $channelId ?? '_global_';
    }
}
