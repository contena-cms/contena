<?php declare(strict_types=1);

namespace CtTestPlugin;

use Contena\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPreActivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPreDeactivateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class CtTestSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PluginPreActivateEvent::class => 'preActivate',
            PluginPostActivateEvent::class => 'postActivate',
            PluginPreDeactivateEvent::class => 'preDeactivate',
            PluginPostDeactivateEvent::class => 'postDeactivate',
        ];
    }

    public function preActivate(PluginPreActivateEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if (!$plugin instanceof CtTestPlugin) {
            return;
        }

        $plugin->preActivateContext = $event->getContext();
    }

    public function postActivate(PluginPostActivateEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if (!$plugin instanceof CtTestPlugin) {
            return;
        }

        $plugin->postActivateContext = $event->getContext();
    }

    public function preDeactivate(PluginPreDeactivateEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if (!$plugin instanceof CtTestPlugin) {
            return;
        }

        $plugin->preDeactivateContext = $event->getContext();
    }

    public function postDeactivate(PluginPostDeactivateEvent $event): void
    {
        $plugin = $event->getContext()->getPlugin();
        if (!$plugin instanceof CtTestPlugin) {
            return;
        }

        $plugin->postDeactivateContext = $event->getContext();
    }
}
