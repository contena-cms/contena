<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem;

use Contena\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Contena\Core\Framework\ContentSystem\Event\PostHydrationEvent;
use Contena\Core\Framework\ContentSystem\Event\PreContentHydrationEvent;
use Contena\Core\Framework\ContentSystem\Hydration\ContentElementHydrator;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @final
 */
class ContentPipeline
{
    public function __construct(
        private readonly ContentElementHydrator $hydrationService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function load(
        RenderableLayout $layout,
        RenderingSpecification $specification,
        RenderingCacheContext $cacheContext,
        RenderingMode $mode,
        ChannelContext $channelContext,
    ): ContentPage {
        $preHydrationEvent = new PreContentHydrationEvent(
            $layout->elements,
            $layout->reference,
            $specification,
            $mode,
            $channelContext,
            $cacheContext,
        );
        $this->eventDispatcher->dispatch($preHydrationEvent);
        $elements = $preHydrationEvent->elements;

        if ($mode === RenderingMode::FULL) {
            $hydratedElementsGenerator = $this->hydrationService->hydrate(
                $elements,
                $channelContext,
                $specification->request,
                $cacheContext,
            );
            $elements = array_values(iterator_to_array($hydratedElementsGenerator, false));
        }

        $afterHydrationEvent = new PostHydrationEvent(
            $elements,
            $layout->reference,
            $specification,
            $mode,
            $channelContext,
            $cacheContext,
        );
        $this->eventDispatcher->dispatch($afterHydrationEvent);

        $reference = $afterHydrationEvent->layout;

        return new ContentPage(
            $reference->id,
            $afterHydrationEvent->elements,
            $reference->name,
            $reference->version,
        );
    }
}
