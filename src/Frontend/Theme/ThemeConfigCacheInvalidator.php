<?php declare(strict_types=1);

namespace Contena\Storefront\Theme;

use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Theme\Event\ThemeAssignedEvent;
use Contena\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Contena\Storefront\Theme\Event\ThemeConfigResetEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class ThemeConfigCacheInvalidator implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ThemeConfigChangedEvent::class => 'invalidate',
            ThemeAssignedEvent::class => 'assigned',
            ThemeConfigResetEvent::class => 'reset',
        ];
    }




    public static function buildCacheTag(string $themeId): string
    {
        return 'theme-config-' . $themeId;
    }
}
