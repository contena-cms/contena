<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Store\Subscriber;

use Contena\Core\Framework\Plugin\PluginEntity;
use Contena\Core\Framework\Store\Event\ExtensionLoadedEvent;
use Contena\Frontend\Framework\ThemeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Marks plugin-backed extensions as themes when Frontend recognizes them.
 *
 * A plugin is a theme when its base class implements {@see ThemeInterface}.
 *
 * @internal
 */
class ExtensionThemeDetectionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ExtensionLoadedEvent::class => 'detectTheme',
        ];
    }

    public function detectTheme(ExtensionLoadedEvent $event): void
    {
        if ($this->isPluginTheme($event->source)) {
            $event->extension->setIsTheme(true);
        }
    }

    private function isPluginTheme(PluginEntity $plugin): bool
    {
        $baseClass = $plugin->getBaseClass();

        return class_exists($baseClass) && is_subclass_of($baseClass, ThemeInterface::class);
    }
}
