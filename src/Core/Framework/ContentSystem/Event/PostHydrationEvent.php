<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Event;

use Contena\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\LayoutReference;
use Contena\Core\Framework\ContentSystem\RenderingMode;
use Contena\Core\Framework\ContentSystem\RenderingSpecification;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;

/**
 * Dispatched after content hydration to allow layout finalization.
 *
 * Replaces dismantling and extraction processes. Subscribers can
 * transform the hydrated elements before response serialization.
 *
 * ## Priority Ranges
 *
 * Subscriber priorities determine execution order (higher = earlier).
 *
 * **Extensions:**
 * - >= 6000: Run BEFORE core processing
 * - < 1000 and >= 0: Run AFTER core processing
 * - < 0: Absolute last (use sparingly)
 *
 * **Core (RESERVED - do not use in extensions):**
 * - >= 5000: Restoration (e.g. unwrapping scaffolding)
 * - >= 3000: Enrichment (e.g. computed data)
 * - >= 1000: Extraction (e.g. partial render, output)
 *
 * @final
 */
class PostHydrationEvent implements ContenaChannelEvent
{
    /**
     * @param list<ContentElement> $elements
     */
    public function __construct(
        public array $elements,
        public readonly LayoutReference $layout,
        public readonly RenderingSpecification $specification,
        public readonly RenderingMode $mode,
        public readonly ChannelContext $channelContext,
        public readonly RenderingCacheContext $cacheContext,
    ) {
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }
}
