<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Seo\SeoUrlRoute;

use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Frontend\Framework\FrontendFrameworkException;

class NavigationPageSeoUrlRoute implements SeoUrlRouteInterface
{
    final public const ROUTE_NAME = 'frontend.navigation.page';
    final public const DEFAULT_TEMPLATE = '{% for part in category.seoBreadcrumb %}{{ part }}/{% endfor %}';

    /**
     * @internal
     */
    public function __construct(
        private readonly CategoryDefinition $categoryDefinition,
        private readonly CategoryBreadcrumbBuilder $breadcrumbBuilder
    ) {
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->categoryDefinition,
            self::ROUTE_NAME,
            self::DEFAULT_TEMPLATE,
            true,
            'navigationId'
        );
    }

    public function prepareCriteria(Criteria $criteria, ChannelEntity $channel): void
    {
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('active', true),
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsFilter('type', CategoryDefinition::TYPE_FOLDER),
            ]),
        ]));
    }

    public function getMapping(Entity $category, ?ChannelEntity $channel): SeoUrlMapping
    {
        if (!$category instanceof CategoryEntity) {
            throw FrontendFrameworkException::invalidArgument('SEO URL Mapping expects argument to be a CategoryEntity');
        }

        $rootId = $this->detectRootId($category, $channel);

        $categoryJson = $category->jsonSerialize();
        $categoryJson['seoBreadcrumb'] = $this->breadcrumbBuilder->build($category, $channel, $rootId);

        $error = null;
        if (!$rootId) {
            $error = 'Category is not available for channel';
        }

        return new SeoUrlMapping(
            $category,
            $this->getConfig()->getPrimaryKeyParameter($category->getId()),
            [
                'category' => $categoryJson,
            ],
            $error
        );
    }

    private function detectRootId(CategoryEntity $category, ?ChannelEntity $channel): ?string
    {
        if (!$channel) {
            return null;
        }
        $path = array_filter(explode('|', (string) $category->getPath()));

        $navigationId = $channel->getNavigationCategoryId();
        if ($navigationId === $category->getId() || \in_array($navigationId, $path, true)) {
            return $navigationId;
        }

        $footerId = $channel->getFooterCategoryId();
        if ($footerId === $category->getId() || \in_array($footerId, $path, true)) {
            return $footerId;
        }

        $serviceId = $channel->getServiceCategoryId();
        if ($serviceId === $category->getId() || \in_array($serviceId, $path, true)) {
            return $serviceId;
        }

        return null;
    }
}
