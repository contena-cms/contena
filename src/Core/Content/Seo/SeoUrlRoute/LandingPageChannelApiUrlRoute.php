<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrlRoute;

use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelEntity;

class LandingPageChannelApiUrlRoute implements EntitySeoUrlRouteInterface
{
    final public const string ROUTE_NAME = 'channel-api.landing-page.detail';

    /**
     * @internal
     */
    public function __construct(private readonly LandingPageDefinition $landingPageDefinition)
    {
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->landingPageDefinition,
            self::ROUTE_NAME,
            '',
            true,
            'landingPageId'
        );
    }

    public function prepareCriteria(Criteria $criteria, ChannelEntity $channel): void
    {
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('channels.id', $channel->getId()));
    }
}
