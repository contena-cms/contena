<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Channel;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryException;
use Contena\Core\Content\Category\Service\DefaultCategoryLevelLoaderInterface;
use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Content\Category\Tree\CategoryTreePathResolver;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @phpstan-type CategoryMetaInformation array{id: string, level: string, path: string}
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class NavigationRoute extends AbstractNavigationRoute
{
    final public const ALL_TAG = 'navigation';

    /**
     * @internal
     *
     * @param ChannelRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ChannelRepository $categoryRepository,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly CategoryTreePathResolver $categoryTreePathResolver,
        private readonly DefaultCategoryLevelLoaderInterface $categoryLevelLoader,
    ) {
    }

    public function getDecorated(): AbstractNavigationRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/navigation/{activeId}/{rootId}',
        name: 'channel-api.navigation',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => CategoryDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(
        string $activeId,
        string $rootId,
        Request $request,
        ChannelContext $context,
        Criteria $criteria
    ): NavigationRouteResponse {
        $depth = $request->query->getInt('depth', $request->request->getInt('depth', NavigationLoaderInterface::DEFAULT_DEPTH));

        $metaInfo = $this->getCategoryMetaInfo($activeId, $rootId);

        $active = $this->getMetaInfoById($activeId, $metaInfo);

        $this->cacheTagCollector->addTag(self::ALL_TAG);

        $root = $this->getMetaInfoById($rootId, $metaInfo);

        // Validate the provided category is part of the channel
        $this->validate($activeId, $active['path'], $context);

        $isChild = $this->isChildCategory($activeId, $active['path'], $rootId);

        $activePath = $active['path'];
        // If the provided activeId is not part of the rootId, a fallback to the rootId must be made here.
        // The passed activeId is therefore part of another navigation and must therefore not be loaded.
        // The availability validation has already been done in the `validate` function.
        if (!$isChild) {
            $activeId = $rootId;
            $activePath = $root['path'];
        }

        $categories = $this->categoryLevelLoader->loadLevels(
            $rootId,
            (int) $root['level'],
            $context,
            clone $criteria,
            $depth
        );

        $additionalPathsToLoad = $this->categoryTreePathResolver->getAdditionalPathsToLoad($activeId, $activePath, $rootId, $root['path'], $depth);

        if ($additionalPathsToLoad !== []) {
            $categories->merge($this->loadAdditionalPaths($context, clone $criteria, $additionalPathsToLoad));
        }

        return new NavigationRouteResponse($categories);
    }

    /**
     * @param list<string> $additionalPaths
     */
    private function loadAdditionalPaths(
        ChannelContext $context,
        Criteria $criteria,
        array $additionalPaths
    ): CategoryCollection {
        $criteria->addFilter(new EqualsAnyFilter('path', $additionalPaths));

        $criteria->addAssociation('media');

        $criteria->setLimit(null);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        $levels = $this->categoryRepository->search($criteria, $context)->getEntities();

        return $levels;
    }

    /**
     * @return array<string, CategoryMetaInformation>
     */
    private function getCategoryMetaInfo(string $activeId, string $rootId): array
    {
        $result = $this->connection->fetchAllAssociative('
            # navigation-route::meta-information
            SELECT LOWER(HEX(`id`)), `path`, `level`
            FROM `category`
            WHERE `id` = :activeId OR `id` = :rootId
        ', ['activeId' => Uuid::fromHexToBytes($activeId), 'rootId' => Uuid::fromHexToBytes($rootId)]);

        if (!$result) {
            throw CategoryException::categoryNotFound($activeId);
        }

        /** @var array<string, CategoryMetaInformation> $result */
        $result = FetchModeHelper::groupUnique($result);

        return $result;
    }

    /**
     * @param array<string, CategoryMetaInformation> $metaInfo
     *
     * @return CategoryMetaInformation
     */
    private function getMetaInfoById(string $id, array $metaInfo): array
    {
        if (!\array_key_exists($id, $metaInfo)) {
            throw CategoryException::categoryNotFound($id);
        }

        return $metaInfo[$id];
    }

    private function validate(string $activeId, ?string $path, ChannelContext $context): void
    {
        $ids = array_filter([
            $context->getChannel()->getFooterCategoryId(),
            $context->getChannel()->getServiceCategoryId(),
            $context->getChannel()->getNavigationCategoryId(),
        ]);

        foreach ($ids as $id) {
            if ($this->isChildCategory($activeId, $path, $id)) {
                return;
            }
        }

        throw CategoryException::categoryNotFound($activeId);
    }

    private function isChildCategory(string $activeId, ?string $path, string $rootId): bool
    {
        if ($rootId === $activeId) {
            return true;
        }

        if ($path === null) {
            return false;
        }

        if (mb_strpos($path, '|' . $rootId . '|') !== false) {
            return true;
        }

        return false;
    }
}
