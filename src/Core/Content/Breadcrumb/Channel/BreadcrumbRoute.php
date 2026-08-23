<?php declare(strict_types=1);

namespace Contena\Core\Content\Breadcrumb\Channel;

use Contena\Core\Content\Blog\Exception\BlogNotFoundException;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class BreadcrumbRoute extends AbstractBreadcrumbRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CategoryBreadcrumbBuilder $breadcrumbBuilder,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public function getDecorated(): AbstractBreadcrumbRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/breadcrumb/{id}',
        name: 'channel-api.breadcrumb',
        requirements: ['id' => '[0-9a-f]{32}'],
        methods: [Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(Request $request, ChannelContext $channelContext): BreadcrumbRouteResponse
    {
        $id = $request->attributes->get('id', '');
        $type = $request->query->get('type', 'blog');
        if ($type === 'category') {
            $breadcrumb = $this->getCategories($id, $channelContext);
        } else {
            $breadcrumb = $this->tryToGetCategoriesFromBlogOrCategory(
                $id,
                $request->query->get('referrerCategoryId', ''),
                $channelContext
            );
        }

        $tags = [];
        foreach ($breadcrumb as $item) {
            $tags[] = CategoryRoute::buildName($item->categoryId);
        }
        if ($type === 'blog') {
            $tags[] = EntityCacheKeyGenerator::buildBlogTag($id);
        }
        if ($tags !== []) {
            $this->cacheTagCollector->addTag(...$tags);
        }

        return new BreadcrumbRouteResponse($breadcrumb);
    }

    private function getCategories(string $id, ChannelContext $channelContext): BreadcrumbCollection
    {
        $category = $this->breadcrumbBuilder->loadCategory($id, $channelContext->getContext());

        if ($category === null) {
            return new BreadcrumbCollection();
        }

        return $this->breadcrumbBuilder->getCategoryBreadcrumbUrls(
            $category,
            $channelContext->getContext(),
            $channelContext->getChannel()
        );
    }

    /**
     * Simple helper function to retry with category type if blog is not found
     */
    private function tryToGetCategoriesFromBlogOrCategory(string $id, string $referrerCategoryId, ChannelContext $channelContext): BreadcrumbCollection
    {
        try {
            $categories = $this->breadcrumbBuilder->getBlogBreadcrumbUrls($id, $referrerCategoryId, $channelContext);
        } catch (BlogNotFoundException) {
            $categories = $this->getCategories($id, $channelContext);
        }

        return $categories;
    }
}
