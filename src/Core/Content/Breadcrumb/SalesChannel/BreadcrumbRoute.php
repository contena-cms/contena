<?php declare(strict_types=1);

namespace Contena\Core\Content\Breadcrumb\SalesChannel;

use Contena\Core\Content\Breadcrumb\Extension\BreadcrumbRouteExtension;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Content\Category\SalesChannel\CategoryRoute;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Product\Exception\ProductNotFoundException;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class BreadcrumbRoute extends AbstractBreadcrumbRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CategoryBreadcrumbBuilder $breadcrumbBuilder,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractBreadcrumbRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/breadcrumb/{id}',
        name: 'store-api.breadcrumb',
        requirements: ['id' => '[0-9a-f]{32}'],
        methods: [Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(Request $request, SalesChannelContext $salesChannelContext): BreadcrumbRouteResponse
    {
        return $this->extensions->publish(
            name: BreadcrumbRouteExtension::NAME,
            extension: new BreadcrumbRouteExtension($request, $salesChannelContext),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, SalesChannelContext $salesChannelContext): BreadcrumbRouteResponse
    {
        $id = $request->attributes->get('id', '');
        $type = $request->query->get('type', 'product');
        if ($type === 'category') {
            $breadcrumb = $this->getCategories($id, $salesChannelContext);
        } else {
            $breadcrumb = $this->tryToGetCategoriesFromProductOrCategory(
                $id,
                $request->query->get('referrerCategoryId', ''),
                $salesChannelContext
            );
        }

        $tags = [];
        foreach ($breadcrumb as $item) {
            $tags[] = CategoryRoute::buildName($item->categoryId);
        }
        if ($type === 'product') {
            $tags[] = EntityCacheKeyGenerator::buildProductTag($id);
        }
        if ($tags !== []) {
            $this->cacheTagCollector->addTag(...$tags);
        }

        return new BreadcrumbRouteResponse($breadcrumb);
    }

    private function getCategories(string $id, SalesChannelContext $salesChannelContext): BreadcrumbCollection
    {
        $category = $this->breadcrumbBuilder->loadCategory($id, $salesChannelContext->getContext());

        if ($category === null) {
            return new BreadcrumbCollection();
        }

        return $this->breadcrumbBuilder->getCategoryBreadcrumbUrls(
            $category,
            $salesChannelContext->getContext(),
            $salesChannelContext->getSalesChannel()
        );
    }

    /**
     * Simple helper function to retry with category type if product is not found
     */
    private function tryToGetCategoriesFromProductOrCategory(string $id, string $referrerCategoryId, SalesChannelContext $salesChannelContext): BreadcrumbCollection
    {
        try {
            $categories = $this->breadcrumbBuilder->getProductBreadcrumbUrls($id, $referrerCategoryId, $salesChannelContext);
        } catch (ProductNotFoundException) {
            $categories = $this->getCategories($id, $salesChannelContext);
        }

        return $categories;
    }
}
