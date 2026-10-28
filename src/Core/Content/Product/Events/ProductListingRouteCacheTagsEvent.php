<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Events;

use Contena\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Contena\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0 as it was not used anymore
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductListingRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
    /**
     * @param array<string|null> $tags
     * @param ProductListingRouteResponse $response
     */
    public function __construct(
        array $tags,
        protected string $categoryId,
        Request $request,
        StoreApiResponse $response,
        SalesChannelContext $context,
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
