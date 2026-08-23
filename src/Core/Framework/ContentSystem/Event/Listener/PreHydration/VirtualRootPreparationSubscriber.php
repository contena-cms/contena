<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Event\Listener\PreHydration;

use Contena\Core\Framework\ContentSystem\Event\PreContentHydrationEvent;
use Contena\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Wraps layout roots with temporary virtual root to distribute layout-level data as context.
 *
 * Virtual root removed after hydration by VirtualRootCleanupSubscriber.
 *
 * @internal
 *
 * @final
 */
#[AsEventListener(event: PreContentHydrationEvent::class, priority: 5000)]
class VirtualRootPreparationSubscriber
{
    public function __construct(
        private readonly VirtualRootWrapper $virtualRootWrapper
    ) {
    }

    public function __invoke(PreContentHydrationEvent $event): void
    {
        if (!$this->virtualRootWrapper->requiresWrapping($event->specification, $event->elements)) {
            return;
        }

        $event->elements = [$this->virtualRootWrapper->wrap($event->elements, $event->specification)];
    }
}
