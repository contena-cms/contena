<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Channel;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\CategoryException;
use Contena\Core\Content\Category\Extension\CategoryRouteExtension;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\Channel\ChannelCmsPageLoaderInterface;
use Contena\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
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
        private readonly ChannelCmsPageLoaderInterface $cmsPageLoader,
        private readonly EntityCmsSlotConfigInheritanceBuilder $cmsSlotConfigInheritanceBuilder,
        private readonly CategoryDefinition $categoryDefinition,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
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
        path: '/store-api/category/{navigationId}',
        name: 'store-api.category.detail',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(string $navigationId, Request $request, ChannelContext $context): CategoryRouteResponse
    {
        return $this->extensions->publish(
            name: CategoryRouteExtension::NAME,
            extension: new CategoryRouteExtension($navigationId, $request, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $navigationId, Request $request, ChannelContext $context): CategoryRouteResponse
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
                return new CategoryRouteResponse($category);
            }

            throw CategoryException::categoryNotFound($navigationId);
        }

        $pageId = $category->getCmsPageId();
        $channel = $context->getChannel();

        if ($category->getId() === $channel->getNavigationCategoryId() && $channel->getHomeCmsPageId()) {
            $pageId = $channel->getHomeCmsPageId();
            $slotConfig = $channel->getTranslation('homeSlotConfig');
        } else {
            $slotConfig = $this->buildMergedCmsSlotConfig($category, $context);
        }

        if (!$pageId) {
            return new CategoryRouteResponse($category);
        }

        $resolverContext = new EntityResolverContext($context, $request, $this->categoryDefinition, $category);

        $pages = $this->cmsPageLoader->load(
            $request,
            $this->createCriteria($pageId, $request),
            $context,
            $slotConfig,
            $resolverContext,
        );

        $cmsPage = $pages->getEntities()->first();
        if ($cmsPage === null) {
            throw CategoryException::pageNotFound($pageId);
        }

        $category->setCmsPage($cmsPage);
        $category->setCmsPageId($pageId);

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

    private function createCriteria(string $pageId, Request $request): Criteria
    {
        $criteria = new Criteria([$pageId]);
        $criteria->setTitle('category::cms-page');

        $slots = RequestParamHelper::get($request, 'slots');

        if (\is_string($slots)) {
            $slots = explode('|', $slots);
        }

        if (\is_array($slots) && $slots !== []) {
            $criteria
                ->getAssociation('sections.blocks')
                ->addFilter(new EqualsAnyFilter('slots.id', $slots));
        }

        return $criteria;
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private function buildMergedCmsSlotConfig(CategoryEntity $category, ChannelContext $context): ?array
    {
        return $this->cmsSlotConfigInheritanceBuilder->build(
            $category->getTranslations(),
            $context,
        );
    }
}
