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
 * Dispatched before content hydration to allow layout preparation.
 *
 * Replaces scaffolding and refinery processes. Subscribers can modify
 * the elements array before data loading begins.
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
 * - >= 5000: Structure (e.g. scaffolding, wrapping)
 * - >= 3000: Transform (e.g. overrides, placeholders)
 * - >= 1000: Pruning (e.g. filtering, partial render)
 *
 * @final
 */
class PreContentHydrationEvent implements ContenaChannelEvent
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
