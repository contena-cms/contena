<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Channel;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\CategoryException;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
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
     * @internal
     *
     * @param ChannelRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly ChannelRepository $categoryRepository,
        private readonly CacheTagCollector $cacheTagCollector,
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
        $this->cacheTagCollector->addTag(self::buildName($navigationId));

        if ($navigationId === self::HOME) {
            $navigationId = $context->getChannel()->getNavigationCategoryId();
            $request->attributes->set('navigationId', $navigationId);

            $routeParams = $request->attributes->get('_route_params', []);
            $routeParams['navigationId'] = $navigationId;
            $request->attributes->set('_route_params', $routeParams);
        }

        $category = $this->loadCategory($navigationId, $context);

        $categoryHasContentlessPageType = \in_array($category->getType(), [CategoryDefinition::TYPE_FOLDER, CategoryDefinition::TYPE_LINK], true);
        if ($categoryHasContentlessPageType && $context->getChannel()->getNavigationCategoryId() !== $navigationId) {
            if ($category->getType() === CategoryDefinition::TYPE_LINK) {
                return new CategoryRouteResponse($category);
            }

            throw CategoryException::categoryNotFound($navigationId);
        }

        return new CategoryRouteResponse($category);
    }

    private function loadCategory(string $categoryId, ChannelContext $context): CategoryEntity
    {
        $criteria = new Criteria([$categoryId]);
        $criteria->setTitle('category::data');

        $criteria->addAssociation('media');
        $criteria->addAssociation('translations');

        $category = $this->categoryRepository->search($criteria, $context)->getEntities()->get($categoryId);
        if (!$category instanceof CategoryEntity) {
            throw CategoryException::categoryNotFound($categoryId);
        }

        return $category;
    }
}
