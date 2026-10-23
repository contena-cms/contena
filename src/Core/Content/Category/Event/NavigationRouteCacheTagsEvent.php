<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Event;

use Contena\Core\Framework\Adapter\Cache\ChannelApiRouteCacheTagsEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelApiResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0 as it was not used anymore
 *
 * @codeCoverageIgnore
 */
class NavigationRouteCacheTagsEvent extends ChannelApiRouteCacheTagsEvent
{
    /**
     * @param array<string> $tags
     */
    public function __construct(
        array $tags,
        protected string $active,
        protected string $rootId,
        protected int $depth,
        Request $request,
        ChannelApiResponse $response,
        ChannelContext $context,
        Criteria $criteria
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        parent::__construct($tags, $request, $response, $context, $criteria);
    }

    public function getActive(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return $this->active;
    }

    public function getRootId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return $this->rootId;
    }

    public function getDepth(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return $this->depth;
    }
}
