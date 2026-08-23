<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrlRoute;

use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\System\Channel\ChannelEntity;

class BlogChannelApiUrlRoute implements EntitySeoUrlRouteInterface
{
    final public const string ROUTE_NAME = 'channel-api.blog.detail';

    /**
     * @internal
     */
    public function __construct(private readonly BlogDefinition $blogDefinition)
    {
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->blogDefinition,
            self::ROUTE_NAME,
            '',
            true,
            'blogId'
        );
    }

    public function prepareCriteria(Criteria $criteria, ChannelEntity $channel): void
    {
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('active', true),
            new RangeFilter('visibilities.visibility', [RangeFilter::GTE => BlogVisibilityDefinition::VISIBILITY_LINK]),
            new EqualsFilter('visibilities.channelId', $channel->getId()),
        ]));
    }
}
