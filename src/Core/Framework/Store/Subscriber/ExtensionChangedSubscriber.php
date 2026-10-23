<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Subscriber;

use Contena\Core\Framework\App\AppEvents;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\PluginEvents;
use Contena\Core\Framework\Store\Services\StoreClient;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
readonly class ExtensionChangedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CacheInterface $cache
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::PLUGIN_WRITTEN_EVENT => 'onExtensionChanged',
            AppEvents::APP_WRITTEN_EVENT => 'onExtensionChanged',
        ];
    }

    public function onExtensionChanged(): void
    {
        $this->cache->delete(StoreClient::EXTENSION_LIST_CACHE);
    }
}
