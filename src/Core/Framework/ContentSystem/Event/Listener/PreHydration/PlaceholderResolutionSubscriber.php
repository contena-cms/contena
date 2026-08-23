<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Event\Listener\PreHydration;

use Contena\Core\Framework\ContentSystem\Event\PreContentHydrationEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Resolves {{variable}} placeholders in element properties.
 *
 * Single-pass only, no recursive resolution. Placeholders are replaced
 * with values from RenderingSpecification before hydration starts.
 *
 * @internal
 *
 * @final
 */
#[AsEventListener(event: PreContentHydrationEvent::class, priority: 3000)]
class PlaceholderResolutionSubscriber
{
    public function __invoke(PreContentHydrationEvent $event): void
    {
        // ContentElement is mutable, so changes happen in place
        foreach ($event->elements as $element) {
            $element->replacePlaceholders($event->specification);
        }
    }
}
