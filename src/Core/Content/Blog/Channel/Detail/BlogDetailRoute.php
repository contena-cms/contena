<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Detail;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\Channel\ChannelCmsPageLoaderInterface;
use Contena\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Extension\BlogDetailRouteExtension;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\AbstractBlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\Detail\Event\ResolveVariantIdEvent;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\Profiling\Profiler;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class BlogDetailRoute extends AbstractBlogDetailRoute
{
    private const SKIP_CONFIGURATOR = 'skipConfigurator';
    private const SKIP_CMS_PAGE = 'skipCmsPage';

    /**
     * @internal
     *
     * @param ChannelRepository<ChannelBlogCollection> $blogRepository
     * @param EntityRepository<BlogTranslationCollection> $blogTranslationRepository
     */
    public function __construct(
        private readonly ChannelRepository $blogRepository,
        private readonly EntityRepository $blogTranslationRepository,
        private readonly SystemConfigService $config,
        private readonly Connection $connection,
        private readonly BlogConfiguratorLoader $configuratorLoader,
        private readonly CategoryBreadcrumbBuilder $breadcrumbBuilder,
        private readonly ChannelCmsPageLoaderInterface $cmsPageLoader,
        private readonly EntityCmsSlotConfigInheritanceBuilder $cmsSlotConfigInheritanceBuilder,
        private readonly ChannelBlogDefinition $blogDefinition,
        private readonly AbstractBlogCloseoutFilterFactory $blogCloseoutFilterFactory,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public static function buildName(string $parentId): string
    {
        return EntityCacheKeyGenerator::buildBlogTag($parentId);
    }

    public function getDecorated(): AbstractBlogDetailRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/blog/{blogId}',
        name: 'store-api.blog.detail',
        defaults: [
            PlatformRequest::ATTRIBUTE_ENTITY => BlogDefinition::ENTITY_NAME,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_POST, Request::METHOD_GET]
    )]
    public function load(string $blogId, Request $request, ChannelContext $context, Criteria $criteria): BlogDetailRouteResponse
    {
        return $this->extensions->publish(
            name: BlogDetailRouteExtension::NAME,
            extension: new BlogDetailRouteExtension($blogId, $request, $context, $criteria),
            function: $this->_load(...),
        );
    }

    private function _load(string $blogId, Request $request, ChannelContext $context, Criteria $criteria): BlogDetailRouteResponse
    {
        return Profiler::trace('blog-detail-route', function () use ($blogId, $request, $context, $criteria) {
            $requestedBlogId = $blogId;
            [$mainVariantId, $parentBlogId] = $this->checkVariantListingConfig($blogId, $context);
            $searchVariantId = $this->resolveSearchVariantId(
                $requestedBlogId,
                $parentBlogId,
                $this->getSearchTerm($request),
                $context
            );
            $resolveVariantIdEvent = new ResolveVariantIdEvent(
                $blogId,
                $searchVariantId ?? $mainVariantId,
                $context,
            );

            $this->dispatcher->dispatch($resolveVariantIdEvent);

            $blogId = $this->resolveCandidateBlogId(
                $requestedBlogId,
                $parentBlogId,
                $resolveVariantIdEvent->getResolvedVariantId(),
                $context
            );

            $this->addFilters($context, $criteria);

            $criteria->setIds([$blogId]);
            $criteria->setTitle('blog-detail-route');

            $loadCmsPage = !$request->query->getBoolean(self::SKIP_CMS_PAGE);
            $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();

            if (!$blog instanceof ChannelBlogEntity && $mainVariantId !== null && $this->isParentBlogRequest($requestedBlogId, $parentBlogId)) {
                $blogId = $this->findBestVariant($requestedBlogId, $context);
                $criteria->setIds([$blogId]);
                $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();
            }

            if (!$blog instanceof ChannelBlogEntity || $this->isParentWithoutResolvedVariant($blog, $resolveVariantIdEvent)) {
                throw BlogException::blogNotFound($blogId);
            }

            $parent = $blog->getParentId() ?? $blog->getId();

            $this->cacheTagCollector->addTag(EntityCacheKeyGenerator::buildBlogTag($parent));

            $blog->setSeoCategory(
                $this->getBreadcrumbCategory($request, $blog, $context)
            );

            $loadConfigurator = !$request->query->getBoolean(self::SKIP_CONFIGURATOR);
            $configurator = $loadConfigurator ? $this->configuratorLoader->load($blog, $context) : null;

            $pageId = $blog->getCmsPageId();
            if ($loadCmsPage && $pageId) {
                $slotConfig = $this->buildMergedCmsSlotConfig($blog, $context);

                // clone blog to prevent recursion encoding (see NEXT-17603)
                $resolverContext = new EntityResolverContext($context, $request, $this->blogDefinition, clone $blog);

                $pages = $this->cmsPageLoader->load(
                    $request,
                    $this->createCriteria($pageId, $request),
                    $context,
                    $slotConfig,
                    $resolverContext
                );

                $cmsPage = $pages->getEntities()->first();
                if ($cmsPage instanceof CmsPageEntity) {
                    $blog->setCmsPage($cmsPage);
                }
            }

            return new BlogDetailRouteResponse($blog, $configurator);
        });
    }

    private function addFilters(ChannelContext $context, Criteria $criteria): void
    {
        $criteria->addFilter(
            new BlogAvailableFilter($context->getChannelId(), BlogVisibilityDefinition::VISIBILITY_LINK)
        );

        $this->addCloseoutFilter($context, $criteria);
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private function buildMergedCmsSlotConfig(ChannelBlogEntity $blog, ChannelContext $context): ?array
    {
        return $this->cmsSlotConfigInheritanceBuilder->build(
            $this->loadBlogTranslations($blog, $context),
            $context,
        );
    }

    private function loadBlogTranslations(ChannelBlogEntity $blog, ChannelContext $context): ?BlogTranslationCollection
    {
        $blogIds = array_filter([$blog->getParentId(), $blog->getId()]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('blogId', $blogIds));
        $criteria->addFilter(new EqualsFilter('blogVersionId', $context->getVersionId()));

        $translations = $this->blogTranslationRepository->search($criteria, $context->getContext())->getEntities();

        if ($translations->count() === 0) {
            return null;
        }

        return $this->buildInheritedBlogTranslations($translations, $blog);
    }

    private function buildInheritedBlogTranslations(BlogTranslationCollection $translations, ChannelBlogEntity $blog): BlogTranslationCollection
    {
        $effectiveTranslations = [];
        $parentId = $blog->getParentId();

        foreach ($translations as $translation) {
            if ($translation->getSlotConfig() === null) {
                continue;
            }

            $languageId = $translation->getLanguageId();

            if ($translation->getBlogId() === $parentId) {
                $effectiveTranslations[$languageId] ??= $translation;

                continue;
            }

            if ($translation->getBlogId() === $blog->getId()) {
                $effectiveTranslations[$languageId] = $translation;
            }
        }

        return new BlogTranslationCollection(array_values($effectiveTranslations));
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function checkVariantListingConfig(string $blogId, ChannelContext $context): array
    {
        if (!Uuid::isValid($blogId)) {
            return [null, null];
        }

        $blogData = $this->connection->fetchAssociative(
            '# blog-detail-route::check-variant-listing-config
            SELECT
                variant_listing_config as variantListingConfig,
                parent_id as parentId
            FROM blog
            WHERE id = :id
            AND version_id = :versionId',
            [
                'id' => Uuid::fromHexToBytes($blogId),
                'versionId' => Uuid::fromHexToBytes($context->getVersionId()),
            ]
        );

        if ($blogData === false) {
            return [null, null];
        }

        $mainVariantId = null;
        if ($blogData['variantListingConfig'] !== null) {
            $variantListingConfig = json_decode((string) $blogData['variantListingConfig'], true, 512, \JSON_THROW_ON_ERROR);

            if (
                !isset($variantListingConfig['displayParent'])
                || (bool) $variantListingConfig['displayParent'] !== true
                || isset($variantListingConfig['mainVariantId'])
            ) {
                $mainVariantId = $variantListingConfig['mainVariantId'] ?? null;
            }
        }

        return [$mainVariantId, $blogData['parentId'] ?? $blogId];
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function findBestVariant(string $blogId, ChannelContext $context): string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('blog.parentId', $blogId))
            ->addSorting(new FieldSorting('blog.available', FieldSorting::DESCENDING))
            ->addSorting(new FieldSorting('blog.price'))
            ->setLimit(1);

        $this->addCloseoutFilter($context, $criteria);
        $criteria->setTitle('blog-detail-route::find-best-variant');
        $variantId = $this->blogRepository->searchIds($criteria, $context);

        return $variantId->firstId() ?? $blogId;
    }

    // the parent itself is only loaded when none of its variants could be resolved
    private function isParentWithoutResolvedVariant(ChannelBlogEntity $blog, ResolveVariantIdEvent $event): bool
    {
        return $blog->getParentId() === null
            && $blog->getChildCount() > 0
            && $blog->getId() !== $event->getResolvedVariantId();
    }

    private function findBestVariantByTerm(string $term, string $blogId, ChannelContext $context): ?string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('blog.parentId', $blogId))
            ->setLimit(1);

        $this->addCloseoutFilter($context, $criteria);
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm($term);

        $criteria->setTitle('blog-detail-route::find-best-variant-by-term');

        return $this->blogRepository->searchIds($criteria, $context)->firstId();
    }

    private function addCloseoutFilter(ChannelContext $context, Criteria $criteria): void
    {
        if (!$this->hideCloseoutBlogsWhenOutOfStock($context)) {
            return;
        }

        $criteria->addFilter($this->blogCloseoutFilterFactory->create($context));
    }

    private function hideCloseoutBlogsWhenOutOfStock(ChannelContext $context): bool
    {
        return $this->config->getBool('core.listing.hideCloseoutBlogsWhenOutOfStock', $context->getChannelId());
    }

    private function getSearchTerm(Request $request): ?string
    {
        $searchTerm = $request->query->get('search');

        if (!\is_string($searchTerm) || $searchTerm === '') {
            return null;
        }

        return $searchTerm;
    }

    private function resolveSearchVariantId(
        string $requestedBlogId,
        ?string $parentBlogId,
        ?string $searchTerm,
        ChannelContext $context
    ): ?string {
        if (
            $searchTerm === null
            || !$this->isParentBlogRequest($requestedBlogId, $parentBlogId)
            || !$this->config->getBool('core.listing.findBestVariant', $context->getChannelId())
        ) {
            return null;
        }

        return $this->findBestVariantByTerm($searchTerm, $requestedBlogId, $context);
    }

    private function resolveCandidateBlogId(
        string $requestedBlogId,
        ?string $parentBlogId,
        ?string $resolvedVariantId,
        ChannelContext $context
    ): string {
        if ($resolvedVariantId !== null) {
            return $resolvedVariantId;
        }

        if (!$this->isParentBlogRequest($requestedBlogId, $parentBlogId)) {
            return $requestedBlogId;
        }

        return $this->findBestVariant($requestedBlogId, $context);
    }

    private function isParentBlogRequest(string $requestedBlogId, ?string $parentBlogId): bool
    {
        return $parentBlogId !== null && $requestedBlogId === $parentBlogId;
    }

    private function createCriteria(string $pageId, Request $request): Criteria
    {
        $criteria = new Criteria([$pageId]);
        $criteria->setTitle('blog::cms-page');

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

    private function getBreadcrumbCategory(Request $request, ChannelBlogEntity $blog, ChannelContext $context): ?CategoryEntity
    {
        if (Feature::isActive('BREADCRUMB_REWORK')) {
            if ($this->config->getBool('core.listing.buildBreadcrumbByReferrerCategory', $context->getChannelId())) {
                $referrerCategoryId = $request->query->get('referrerCategoryId');

                if ($referrerCategoryId !== null) {
                    return $this->breadcrumbBuilder->getBlogCategoryByReferrer($referrerCategoryId, $blog, $context);
                }
            }
        }

        return $this->breadcrumbBuilder->getBlogSeoCategory($blog, $context);
    }
}
