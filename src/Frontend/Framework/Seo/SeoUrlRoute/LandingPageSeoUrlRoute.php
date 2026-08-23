<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Seo\SeoUrlRoute;

use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Frontend\Framework\FrontendFrameworkException;

class LandingPageSeoUrlRoute implements SeoUrlRouteInterface
{
    final public const ROUTE_NAME = 'frontend.landing.page';
    final public const DEFAULT_TEMPLATE = '{{ landingPage.translated.url }}';

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
            self::DEFAULT_TEMPLATE,
            true,
            'landingPageId'
        );
    }

    public function prepareCriteria(Criteria $criteria, ChannelEntity $channel): void
    {
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('channels.id', $channel->getId()));
    }

    public function getMapping(Entity $landingPage, ?ChannelEntity $channel): SeoUrlMapping
    {
        if (!$landingPage instanceof LandingPageEntity) {
            throw FrontendFrameworkException::invalidArgument('SEO URL Mapping expects argument to be a LandingPageEntity');
        }

        return new SeoUrlMapping(
            $landingPage,
            $this->getConfig()->getPrimaryKeyParameter($landingPage->getId()),
            [
                'landingPage' => $landingPage->jsonSerialize(),
            ]
        );
    }
}
