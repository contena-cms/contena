<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage\Event;

use Contena\Core\Content\LandingPage\Channel\LandingPageRouteResponse;
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
class LandingPageRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
    /**
     * @param array<string|null> $tags
     * @param LandingPageRouteResponse $response
     */
    public function __construct(
        protected string $landingPageId,
        array $tags,
        Request $request,
        StoreApiResponse $response,
        ChannelContext $context,
        ?Criteria $criteria
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        parent::__construct($tags, $request, $response, $context, $criteria);
    }

    public function getLandingPageId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return $this->landingPageId;
    }
}
