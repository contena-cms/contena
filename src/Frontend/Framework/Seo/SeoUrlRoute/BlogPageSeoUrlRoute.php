<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Seo\SeoUrlRoute;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Frontend\Framework\FrontendFrameworkException;

class BlogPageSeoUrlRoute implements SeoUrlRouteInterface
{
    final public const ROUTE_NAME = 'frontend.blog.detail.page';
    final public const DEFAULT_TEMPLATE = '{{ blog.translated.name }}';

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
            self::DEFAULT_TEMPLATE,
            true,
            'blogId'
        );
    }

    public function prepareCriteria(Criteria $criteria, ChannelEntity $channel): void
    {
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('visibilities.channelId', $channel->getId()));
    }

    public function getMapping(Entity $blog, ?ChannelEntity $channel): SeoUrlMapping
    {
        if (!$blog instanceof BlogEntity && !$blog instanceof PartialEntity) {
            throw FrontendFrameworkException::invalidArgument('SEO URL Mapping expects argument to be a BlogEntity');
        }

        $categories = $blog->get('mainCategories');
        if ($categories instanceof EntityCollection && $channel !== null) {
            $filtered = $categories->filter(
                static fn (Entity $category) => $category->get('channelId') === $channel->getId()
            );

            $blog->assign(['mainCategories' => $filtered]);
        }

        return new SeoUrlMapping(
            $blog,
            $this->getConfig()->getPrimaryKeyParameter($blog->getId()),
            [
                'blog' => $blog->jsonSerialize(),
            ]
        );
    }
}
