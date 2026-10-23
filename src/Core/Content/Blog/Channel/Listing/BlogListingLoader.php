<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Blog\Events\BlogListingPreviewCriteriaEvent;
use Contena\Core\Content\Blog\Events\BlogListingResolvePreviewEvent;
use Contena\Core\Content\Blog\Extension\LoadPreviewExtension;
use Contena\Core\Content\Blog\Extension\ResolveListingExtension;
use Contena\Core\Content\Blog\Extension\ResolveListingIdsExtension;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\AbstractBlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Content\Blog\Channel\Search\ResolvedCriteriaBlogSearchRoute;
use Contena\Core\Content\Blog\Channel\Suggest\BlogSuggestRoute;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BlogListingLoader
{
    /**
     * Criteria state that suppresses the variant grouping (`displayGroup` field grouping) otherwise
     * applied to blog listings. Set by {@see AbstractContentStreamBuilder::enrichCriteria()}
     * when a blog stream is configured to not display its variants as a group.
     */
    final public const STATE_SKIP_ADD_GROUPING = 'skipAddGrouping';

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Reduced listing loading now uses Criteria::excludeFields()
     *             to drop heavy columns instead of allow-listing fields.
     *
     * @var list<string>
     */
    final public const PARTIAL_LISTING_FIELDS = [
        'id',
        'versionId',
        'parentId',
        'blogNumber',
        'displayGroup',
        'states',
        'childCount',
        'name',
        'descriptionTeaser',
        'available',
        'availableStock',
        'stock',
        'isCloseout',
        'minPurchase',
        'maxPurchase',
        'purchaseSteps',
        'purchaseUnit',
        'referenceUnit',
        'unitId',
        'taxId',
        'price',
        'prices.ruleId',
        'prices.price',
        'prices.quantityStart',
        'prices.quantityEnd',
        'cheapestPrice',
        'variantListingConfig',
        'variation',
        'options.group',
        'coverId',
        'cover.media.url',
        'cover.media.alt',
        'cover.media.title',
        'cover.media.mediaTypeRaw',
        'cover.media.thumbnailsRo',
        'manufacturerId',
        'manufacturer.name',
        'ratingAverage',
        'releaseDate',
        'markAsTopseller',
        'deliveryTimeId',
        'deliveryTime.name',
        'deliveryTime.min',
        'deliveryTime.max',
        'deliveryTime.unit',
    ];

    /**
     * Heavy, off-page columns dropped from listings via {@see Criteria::excludeFields()} when
     * `core.listing.partialDataLoading` is enabled.
     *
     * @var list<string>
     */
    final public const PARTIAL_LISTING_EXCLUDED_FIELDS = [
        'description',
        'keywords',
        'customSearchKeywords',
    ];

    /**
     * @internal
     *
     * @param ChannelRepository<BlogCollection> $blogRepository
     */
    public function __construct(
        private readonly ChannelRepository $blogRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly AbstractBlogCloseoutFilterFactory $blogCloseoutFilterFactory,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    /**
     * @return EntitySearchResult<BlogCollection>
     */
    public function load(Criteria $origin, ChannelContext $context): EntitySearchResult
    {
        // allows full service decoration
        return $this->extensions->publish(
            name: ResolveListingExtension::NAME,
            extension: new ResolveListingExtension($origin, $context),
            function: $this->_load(...)
        );
    }

    /**
     * @return EntitySearchResult<BlogCollection>
     */
    private function _load(Criteria $criteria, ChannelContext $context): EntitySearchResult
    {
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $partialDataLoading = $this->systemConfigService->getBool('core.listing.partialDataLoading', $context->getChannelId());

        if ($criteria->getFields() === [] && $criteria->getExcludedFields() === [] && $partialDataLoading) {
            $criteria->excludeFields(self::PARTIAL_LISTING_EXCLUDED_FIELDS);
        }

        $clone = clone $criteria;

        $idResult = $this->extensions->publish(
            name: ResolveListingIdsExtension::NAME,
            extension: new ResolveListingIdsExtension($clone, $context),
            function: $this->resolveIds(...)
        );

        $aggregations = $this->blogRepository->aggregate($clone, $context);

        $ids = $idResult->getIds();
        // no blogs found, no need to continue
        if ($ids === []) {
            $result = new EntitySearchResult(
                BlogDefinition::ENTITY_NAME,
                0,
                new BlogCollection(),
                $aggregations,
                $criteria,
                $context->getContext()
            );

            $result->addState(...$idResult->getStates());
            $result->addExtensions($idResult->getExtensions());

            return $result;
        }

        $mapping = $this->resolvePreviews($ids, $clone, $context);

        $blogSearchResult = $this->resolveData($clone, $mapping, $context);

        $this->addExtensions($clone, $idResult, $blogSearchResult, $mapping);

        $result = new EntitySearchResult(BlogDefinition::ENTITY_NAME, $idResult->getTotal(), $blogSearchResult->getEntities(), $aggregations, $criteria, $context->getContext());
        $result->addState(...$idResult->getStates());
        $result->addExtensions($blogSearchResult->getExtensions());

        return $result;
    }

    private function hasOptionFilter(Criteria $criteria): bool
    {
        $filters = $criteria->getPostFilters();

        $fields = [];
        foreach ($filters as $filter) {
            array_push($fields, ...$filter->getFields());
        }

        $fields = array_map(static fn (string $field) => preg_replace('/^blog./', '', $field), $fields);

        if (\in_array('options.id', $fields, true) || \in_array('properties.id', $fields, true)) {
            return true;
        }

        return \in_array('optionIds', $fields, true) || \in_array('propertyIds', $fields, true);
    }

    private function addGrouping(Criteria $criteria): void
    {
        $criteria->addGroupField(new FieldGrouping('displayGroup'));
        $criteria->addFilter(new NotEqualsFilter('displayGroup', null));
    }

    /**
     * @param array<string> $ids
     * @param list<Filter> $postFilters
     *
     * @throws \JsonException
     *
     * @return array<string>
     */
    private function loadPreviews(array $ids, ChannelContext $context, array $postFilters): array
    {
        $ids = array_combine($ids, $ids);

        $config = $this->connection->fetchAllAssociative(
            '# blog-listing-loader::resolve-previews
            SELECT
                parent.variant_listing_config as variantListingConfig,
                LOWER(HEX(child.id)) as id,
                LOWER(HEX(parent.id)) as parentId
             FROM blog as child
                INNER JOIN blog as parent
                    ON parent.id = child.parent_id
                    AND parent.version_id = child.version_id
             WHERE child.version_id = :version
             AND child.id IN (:ids)',
            [
                'ids' => Uuid::fromHexToBytesList(array_values($ids)),
                'version' => Uuid::fromHexToBytes($context->getContext()->getVersionId()),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        $mapping = [];
        $mainVariantIds = [];
        foreach ($config as $item) {
            if ($item['variantListingConfig'] === null) {
                continue;
            }
            $variantListingConfig = json_decode((string) $item['variantListingConfig'], true, 512, \JSON_THROW_ON_ERROR);

            if (isset($variantListingConfig['mainVariantId']) && $variantListingConfig['mainVariantId']) {
                $mapping[$item['id']] = $variantListingConfig['mainVariantId'];
                $mainVariantIds[$item['id']] = $variantListingConfig['mainVariantId'];
            }

            if (isset($variantListingConfig['displayParent']) && $variantListingConfig['displayParent']) {
                $mapping[$item['id']] = $item['parentId'];
                unset($mainVariantIds[$item['id']]);
            }
        }

        // now we have a mapping for "child => main variant"
        if ($mapping === []) {
            return $ids;
        }

        // filter inactive and not available variants
        $criteria = new Criteria(array_values($mapping));
        $criteria->addFilter(new BlogAvailableFilter($context->getChannelId()));

        if ($this->systemConfigService->getBool(
            'core.listing.hideCloseoutBlogsWhenOutOfStock',
            $context->getChannelId()
        )) {
            $criteria->addFilter(
                $this->blogCloseoutFilterFactory->create($context)
            );
        }

        $this->dispatcher->dispatch(
            new BlogListingPreviewCriteriaEvent($criteria, $context)
        );

        // main variant is only used as preview if it matches the post filters
        // parent is always kept, as it represents all of its variants
        if ($postFilters !== [] && $mainVariantIds !== []) {
            $matchesActiveFilters = new AndFilter($postFilters);
            $parentIds = array_values(array_unique(array_diff($mapping, $mainVariantIds)));

            $criteria->addFilter($parentIds === [] ? $matchesActiveFilters : new OrFilter([
                new EqualsAnyFilter('id', $parentIds),
                $matchesActiveFilters,
            ]));
        }

        $available = $this->blogRepository->searchIds($criteria, $context);

        $remapped = [];
        // replace existing ids with main variant id
        foreach ($ids as $id) {
            // id has no mapped main_variant - keep old id
            if (!isset($mapping[$id])) {
                $remapped[$id] = $id;

                continue;
            }

            // get access to main variant id over the fetched config mapping
            $main = $mapping[$id];

            // main variant is configured but not active/available or does not match the post filters - keep old id
            if (!$available->has($main)) {
                $remapped[$id] = $id;

                continue;
            }

            // main variant is configured and available - add main variant id
            $remapped[$id] = $main;
        }

        return $remapped;
    }

    /**
     * @param EntitySearchResult<BlogCollection> $blogSearchResult
     * @param array<string> $mapping
     */
    private function addExtensions(Criteria $criteria, IdSearchResult $ids, EntitySearchResult $blogSearchResult, array $mapping): void
    {
        foreach ($ids->getExtensions() as $name => $extension) {
            $blogSearchResult->addExtension($name, $extension);
        }

        if ($criteria->hasState(Criteria::STATE_DISABLE_SEARCH_INFO)) {
            return;
        }

        /** @var string $id */
        foreach ($ids->getIds() as $id) {
            if (!isset($mapping[$id])) {
                continue;
            }

            // current id was mapped to another variant
            if (!$blogSearchResult->getEntities()->has($mapping[$id])) {
                continue;
            }

            $blog = $blogSearchResult->getEntities()->get($mapping[$id]);

            // get access to the data of the search result
            $blog->addExtension('search', new ArrayEntity($ids->getDataOfId($id)));
        }
    }

    private function resolveIds(Criteria $criteria, ChannelContext $context): IdSearchResult
    {
        $displayAsGroup = !$this->shouldSkipGrouping($criteria);

        if ($displayAsGroup) {
            $this->addGrouping($criteria);
        }

        $isSearchRoute = $criteria->hasState(ResolvedCriteriaBlogSearchRoute::STATE, BlogSuggestRoute::STATE);

        if ($displayAsGroup && $isSearchRoute && $this->systemConfigService->getBool(
            'core.listing.findBestVariant',
            $context->getChannelId()
        )) {
            $criteria->addState(Criteria::STATE_SCORE_RANKED_GROUPING);
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('childCount', 0),
                new EqualsFilter('childCount', null),
            ]));
        }

        if ($this->systemConfigService->getBool(
            'core.listing.hideCloseoutBlogsWhenOutOfStock',
            $context->getChannelId()
        )) {
            $criteria->addFilter(
                $this->blogCloseoutFilterFactory->create($context)
            );
        }

        return $this->blogRepository->searchIds($criteria, $context);
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, string>
     */
    private function resolvePreviews(array $keys, Criteria $criteria, ChannelContext $context): array
    {
        $mapping = array_combine($keys, $keys);

        $shouldLoadPreviews = !$this->shouldSkipGrouping($criteria) && $this->shouldLoadPreviews($criteria, $context);

        if ($shouldLoadPreviews) {
            $mapping = $this->extensions->publish(
                name: LoadPreviewExtension::NAME,
                extension: new LoadPreviewExtension($keys, $context, $criteria->getPostFilters()),
                function: $this->loadPreviews(...)
            );
        }

        $event = new BlogListingResolvePreviewEvent($context, $criteria, $mapping, $this->hasOptionFilter($criteria));
        $this->dispatcher->dispatch($event);

        return $event->getMapping();
    }

    private function shouldLoadPreviews(Criteria $criteria, ChannelContext $context): bool
    {
        $isSearchRoute = $criteria->hasState(ResolvedCriteriaBlogSearchRoute::STATE, BlogSuggestRoute::STATE);

        return !$isSearchRoute || !$this->systemConfigService->getBool(
            'core.listing.findBestVariant',
            $context->getChannelId()
        );
    }

    /**
     * @param array<string, string> $mapping
     *
     * @return EntitySearchResult<BlogCollection>
     */
    private function resolveData(Criteria $criteria, array $mapping, ChannelContext $context): EntitySearchResult
    {
        $read = $criteria->cloneForRead(array_values($mapping));
        $read->addAssociation('options.group');

        return $this->blogRepository->search($read, $context);
    }

    private function shouldSkipGrouping(Criteria $criteria): bool
    {
        return $criteria->hasState(self::STATE_SKIP_ADD_GROUPING);
    }
}
