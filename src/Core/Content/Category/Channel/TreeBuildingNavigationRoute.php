<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Channel;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\CategoryException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TreeBuildingNavigationRoute extends AbstractNavigationRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractNavigationRoute $decorated)
    {
    }

    public function getDecorated(): AbstractNavigationRoute
    {
        return $this->decorated;
    }

    public function load(string $activeId, string $rootId, Request $request, ChannelContext $context, Criteria $criteria): NavigationRouteResponse
    {
        try {
            $activeId = $this->resolveAliasId($activeId, $context->getChannel());
        } catch (CategoryException $e) {
            if (!$e->is(CategoryException::FOOTER_CATEGORY_NOT_FOUND, CategoryException::SERVICE_CATEGORY_NOT_FOUND)) {
                throw $e;
            }

            $response = new NavigationRouteResponse(new CategoryCollection());
            $response->setStatusCode(Response::HTTP_NO_CONTENT);

            return $response;
        }

        $rootId = $this->resolveAliasId($rootId, $context->getChannel());

        $response = $this->getDecorated()->load($activeId, $rootId, $request, $context, $criteria);

        $buildTree = $request->query->getBoolean('buildTree', $request->request->getBoolean('buildTree', true));

        if (!$buildTree) {
            return $response;
        }

        $categories = $this->buildTree($rootId, $response->getCategories()->getElements());

        return new NavigationRouteResponse($categories);
    }

    /**
     * @param CategoryEntity[] $categories
     */
    private function buildTree(?string $parentId, array $categories): CategoryCollection
    {
        $children = new CategoryCollection();
        foreach ($categories as $key => $category) {
            if ($category->getParentId() !== $parentId) {
                continue;
            }

            unset($categories[$key]);

            $children->add($category);
        }

        $children->sortByPosition();

        $items = new CategoryCollection();
        foreach ($children as $child) {
            if (!$child->getActive() || !$child->getVisible()) {
                continue;
            }

            $child->setChildren($this->buildTree($child->getId(), $categories));

            $items->add($child);
        }

        return $items;
    }

    private function resolveAliasId(string $id, ChannelEntity $channelEntity): string
    {
        $name = $channelEntity->getTranslation('name') ?? '';
        \assert(\is_string($name));

        switch ($id) {
            case 'main-navigation':
                return $channelEntity->getNavigationCategoryId();
            case 'service-navigation':
                if ($channelEntity->getServiceCategoryId() === null) {
                    throw CategoryException::serviceCategoryNotFoundForChannel($name);
                }

                return $channelEntity->getServiceCategoryId();
            case 'footer-navigation':
                if ($channelEntity->getFooterCategoryId() === null) {
                    throw CategoryException::footerCategoryNotFoundForChannel($name);
                }

                return $channelEntity->getFooterCategoryId();
            default:
                return $id;
        }
    }
}
