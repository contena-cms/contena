<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Indexing;

use Contena\Core\Framework\App\Event\AppActivatedEvent;
use Contena\Core\Framework\App\Event\AppDeactivatedEvent;
use Contena\Core\Framework\App\Event\AppDeletedEvent;
use Contena\Core\Framework\App\Event\AppInstalledEvent;
use Contena\Core\Framework\App\Event\AppUpdatedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage;
use Contena\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
class FlowIndexerSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostInstallEvent::class => 'refreshPlugin',
            PluginPostActivateEvent::class => 'refreshPlugin',
            PluginPostUpdateEvent::class => 'refreshPlugin',
            PluginPostDeactivateEvent::class => 'refreshPlugin',
            PluginPostUninstallEvent::class => 'refreshPlugin',
            AppInstalledEvent::class => 'refreshPlugin',
            AppUpdatedEvent::class => 'refreshPlugin',
            AppActivatedEvent::class => 'refreshPlugin',
            AppDeletedEvent::class => 'refreshPlugin',
            AppDeactivatedEvent::class => 'refreshPlugin',
        ];
    }

    public function refreshPlugin(): void
    {
        // Schedule indexer to update flows
        $this->messageBus->dispatch(new IterateEntityIndexerMessage(FlowIndexer::NAME, null));
    }
}
