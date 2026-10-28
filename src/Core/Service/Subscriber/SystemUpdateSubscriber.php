<?php declare(strict_types=1);

namespace Contena\Core\Service\Subscriber;

use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Contena\Core\Service\LifecycleManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class SystemUpdateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LifecycleManager $lifecycleManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UpdatePostFinishEvent::class => 'sync',
        ];
    }

    public function sync(UpdatePostFinishEvent $event): void
    {
        try {
            $this->lifecycleManager->sync($event->getContext());
        } catch (\Throwable $exception) {
            // this should not fail the update process, no matter what.
            $this->logger->error('Failed to sync lifecycle manager', ['exception' => $exception]);
        }
    }
}
