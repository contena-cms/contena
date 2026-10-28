<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage\Event;

use Contena\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0 as it was not used anymore
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class LandingPageRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
    public function __construct(
        protected string $landingPageId,
        array $parts,
        Request $request,
        SalesChannelContext $context,
        ?Criteria $criteria
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        parent::__construct($parts, $request, $context, $criteria);
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
