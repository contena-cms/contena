<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Event\Listener\PreHydration;

use Contena\Core\Framework\ContentSystem\Event\PreContentHydrationEvent;
use Contena\Core\Framework\ContentSystem\Output\PartialRenderer;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Prunes layout tree to target element and dependencies when elementId parameter present.
 *
 * Pre-hydration tree pruning keeps context-dependent ancestors to preserve data flow.
 * Post-hydration extraction (PartialRenderingExtractionSubscriber) removes these ancestors.
 *
 * @internal
 *
 * @final
 */
#[AsEventListener(event: PreContentHydrationEvent::class, priority: 1000)]
class PartialRenderingPreparationSubscriber
{
    public function __construct(
        private readonly PartialRenderer $partialRenderer
    ) {
    }

    public function __invoke(PreContentHydrationEvent $event): void
    {
        $targetElementId = $event->specification->targetElementId;

        if ($targetElementId === null || $targetElementId === '') {
            return;
        }

        $event->elements = $this->partialRenderer->pruneToTarget($event->elements, $targetElementId);
    }
}
