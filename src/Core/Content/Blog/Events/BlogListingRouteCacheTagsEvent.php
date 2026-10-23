<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Events;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingRouteResponse;
use Contena\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0 as it was not used anymore
 *
 * @codeCoverageIgnore
 */
class BlogListingRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
    /**
     * @param array<string|null> $tags
     * @param BlogListingRouteResponse $response
     */
    public function __construct(
        array $tags,
        protected string $categoryId,
        Request $request,
        StoreApiResponse $response,
        ChannelContext $context,
        Criteria $criteria
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        parent::__construct($tags, $request, $response, $context, $criteria);
    }

    public function getCategoryId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return $this->categoryId;
    }
}
