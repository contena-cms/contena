<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache;

use Contena\Core\System\SystemConfig\CachedSystemConfigLoader;
use Contena\Core\System\SystemConfig\Event\SystemConfigMultipleChangedEvent;

/**
 * @internal
 */
class CacheInvalidationSubscriber
{
    /**
     * @internal
     */
    public function __construct(private readonly CacheInvalidator $cacheInvalidator)
    {
    }

    public function invalidateConfig(): void
    {
        $this->cacheInvalidator->invalidate([CachedSystemConfigLoader::CACHE_TAG], true);
    }

    public function invalidateConfigKey(SystemConfigMultipleChangedEvent $event): void
    {
        $this->invalidateConfig();

        if ($event->isSilent()) {
            return;
        }

        $this->cacheInvalidator->invalidate(['system.config-' . $event->getChannelId()]);
    }
}
