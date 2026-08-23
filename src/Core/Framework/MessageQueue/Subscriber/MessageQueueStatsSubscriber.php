<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\Subscriber;

use Contena\Core\Framework\MessageQueue\Stats\StatsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * @internal
 */
class MessageQueueStatsSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly StatsService $statsService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageHandledEvent::class => 'onMessageHandled',
        ];
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $this->statsService->registerMessage($event->getEnvelope());
    }
}
