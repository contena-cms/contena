<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Store;

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
    public function setConfig(?string $channelId, string $contextKey, array $config): void
    {
        $this->configs[$this->getKey($channelId, $contextKey)] = $config;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function getConfig(?string $channelId, string $contextKey): ?array
    {
        return $this->configs[$this->getKey($channelId, $contextKey)] ?? null;
    }

    public function removeConfig(?string $channelId): void
    {
        if ($channelId === null) {
            $this->reset();

            return;
        }

        $prefix = $channelId . ':';
        foreach (array_keys($this->configs) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->configs[$key]);
            }
        }
    }

    public function reset(): void
    {
        $this->configs = [];
    }

    private function getKey(?string $channelId, string $contextKey): string
    {
        return ($channelId ?? '_global_') . ':' . $contextKey;
    }
}
