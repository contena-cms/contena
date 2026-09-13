<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Channel;

use Contena\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryException;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class CategoryRoute extends AbstractCategoryRoute
{
    final public const HOME = 'home';

    /**
     * Opt out of loading the breadcrumb. Clients pass it as the `skipBreadcrumb` query or body parameter; internal
     * callers set it as a request attribute, which takes precedence and cannot be provided by a client.
     */
    final public const SKIP_BREADCRUMB = 'skipBreadcrumb';

    /**
     * @internal
     *
     * @param ChannelRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly ChannelRepository $categoryRepository,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly CategoryBreadcrumbBuilder $breadcrumbBuilder,
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'category-route-' . $id;
    }

    public function getDecorated(): AbstractCategoryRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/category/{navigationId}',
        name: 'channel-api.category.detail',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(string $navigationId, Request $request, ChannelContext $context): CategoryRouteResponse
    {
        if ($navigationId === self::HOME) {
            $navigationId = $context->getChannel()->getNavigationCategoryId();
            $request->attributes->set('navigationId', $navigationId);

            $routeParams = $request->attributes->get('_route_params', []);
            $routeParams['navigationId'] = $navigationId;
            $request->attributes->set('_route_params', $routeParams);
        }

        $this->cacheTagCollector->addTag(self::buildName($navigationId));

        $category = $this->loadCategory($navigationId, $context);

        $categoryHasContentlessPageType = \in_array($category->getType(), [CategoryDefinition::TYPE_FOLDER, CategoryDefinition::TYPE_LINK], true);
        if ($categoryHasContentlessPageType && $context->getChannel()->getNavigationCategoryId() !== $navigationId) {
            if ($category->getType() === CategoryDefinition::TYPE_LINK) {
                $this->addBreadcrumb($request, $category, $context);

                return new CategoryRouteResponse($category);
            }

            // A folder category always results in a 404, so the breadcrumb would be built and thrown away.
            throw CategoryException::categoryNotFound($navigationId);
        }

        $this->addBreadcrumb($request, $category, $context);

        return new CategoryRouteResponse($category);
    }

    private function loadCategory(string $categoryId, ChannelContext $context): ChannelCategoryEntity
    {
        $criteria = new Criteria([$categoryId]);
        $criteria->setTitle('category::data');

        $criteria->addAssociation('media');
        $criteria->addAssociation('translations');

        $category = $this->categoryRepository->search($criteria, $context)->getEntities()->get($categoryId);
        if (!$category instanceof ChannelCategoryEntity) {
            throw CategoryException::categoryNotFound($categoryId);
        }

        return $category;
    }

    private function addBreadcrumb(Request $request, ChannelCategoryEntity $category, ChannelContext $context): void
    {
        if ($this->skipBreadcrumb($request)) {
            return;
        }

        $breadcrumb = $this->breadcrumbBuilder->getCategoryBreadcrumbUrls(
            $category,
            $context->getContext(),
            $context->getChannel()
        );

        $category->setSeoBreadcrumb($breadcrumb);

        // The breadcrumb reflects every category on the path, so all of them invalidate the cached response.
        $tags = $breadcrumb->map(static fn (Breadcrumb $item) => self::buildName($item->categoryId));

        if ($tags !== []) {
            $this->cacheTagCollector->addTag(...$tags);
        }
    }

    /**
     * Internal callers opt out via a request attribute, which a client cannot set. Only when no attribute is present
     * is the client parameter honoured, and it is read leniently so malformed input cannot turn a page into a 400.
     */
    private function skipBreadcrumb(Request $request): bool
    {
        if ($request->attributes->has(self::SKIP_BREADCRUMB)) {
            return $request->attributes->getBoolean(self::SKIP_BREADCRUMB);
        }

        return filter_var(RequestParamHelper::get($request, self::SKIP_BREADCRUMB, false), \FILTER_VALIDATE_BOOL);
    }
}
