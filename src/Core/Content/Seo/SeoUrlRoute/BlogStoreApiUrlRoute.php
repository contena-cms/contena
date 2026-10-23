<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrlRoute;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelEntity;

class BlogStoreApiUrlRoute implements EntitySeoUrlRouteInterface
{
    final public const ROUTE_NAME = 'store-api.blog.detail';

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
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('visibilities.channelId', $channel->getId()));
        $criteria->addAssociation('options.group');
    }
}
