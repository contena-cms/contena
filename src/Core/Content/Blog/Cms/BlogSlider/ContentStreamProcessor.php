<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cms\BlogSlider;

use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\Blog\Events\BlogSliderStreamCriteriaEvent;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\BlogSliderStruct;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Contena\Core\Content\ContentStream\Exception\EmptyContentStreamException;
use Contena\Core\Content\ContentStream\Exception\NoFilterException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

class ContentStreamProcessor extends AbstractBlogSliderProcessor
{
    private const FALLBACK_LIMIT = 50;


    public function getDecorated(): AbstractBlogSliderProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function getSource(): string
    {
        return 'content_stream';
    }

    public function collect(CmsSlotEntity $slot, FieldConfigCollection $config, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $blogs = $config->get('blogs');
        \assert($blogs instanceof FieldConfig);
        $criteria = $this->collectByBlogStream($resolverContext, $blogs, $config);
        if ($criteria === null) {
            return null;
        }

        $this->eventDispatcher->dispatch(new BlogSliderStreamCriteriaEvent($slot, $criteria, $resolverContext->getChannelContext()));

        $collection = new CriteriaCollection();
        $collection->add(self::BLOG_SLIDER_ENTITY_FALLBACK . '_' . $slot->getUniqueIdentifier(), BlogDefinition::class, $criteria);

        return $collection;
    }

    public function enrich(CmsSlotEntity $slot, ElementDataCollection $result, ResolverContext $resolverContext): void
    {
        $entitySearchResult = $result->get(self::BLOG_SLIDER_ENTITY_FALLBACK . '_' . $slot->getUniqueIdentifier());
        if (!$entitySearchResult) {
            return;
        }

        $streamResult = $entitySearchResult->getEntities();
        if (!$streamResult instanceof BlogCollection) {
            return;
        }

        $slider = new BlogSliderStruct();
        $slot->setData($slider);

        $context = $resolverContext->getChannelContext();

        $blogs = $this->handleBlogStream(
            $streamResult,
            $context,
            $entitySearchResult->getCriteria()
        );

        // Safety net: handleBlogStream() can remap stream hits to their display
        // parent or main variant, which may itself be a hidden closeout blog even
        // though the stream criteria already filtered unavailable closeout blogs.
        if ($this->hideUnavailableBlogs($context)) {
            $blogs = $this->filterOutOutOfStockHiddenCloseoutBlogs($blogs);
        }

        $slider->setBlogs($blogs);

        $config = $slot->getFieldConfig();

        $blogConfig = $config->get('blogs');
        \assert($blogConfig instanceof FieldConfig);

        $slider->setStreamId($blogConfig->getStringValue());
    }

    private function collectByBlogStream(
        ResolverContext $resolverContext,
        FieldConfig $config,
        FieldConfigCollection $elementConfig
    ): ?Criteria {
        $limit = $elementConfig->get('contentStreamLimit')?->getIntValue() ?? self::FALLBACK_LIMIT;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addAssociation('options.group');

        try {
            $contentStreamBuilder = $this->contentStreamBuilder;
            if ($contentStreamBuilder instanceof AbstractContentStreamBuilder) {
                $contentStreamBuilder->enrichCriteria(
                    $criteria,
                    $config->getStringValue(),
                    $resolverContext->getChannelContext()->getContext()
                );
            } else {
                $criteria->addFilter(...$contentStreamBuilder->buildFilters(
                    $config->getStringValue(),
                    $resolverContext->getChannelContext()->getContext()
                ));
            }
        } catch (EntityNotFoundException $exception) {
            $this->logger->warning(
                'Blog stream configured for CMS blog slider could not be found.',
                [
                    'contentStreamId' => $config->getStringValue(),
                    'exception' => $exception,
                ]
            );

            return null;
        } catch (EmptyContentStreamException) {
            // An empty group selects all blogs, as in the blog export
        } catch (NoFilterException $exception) {
            $this->logger->warning(
                'Blog stream configured for CMS blog slider has no usable filters.',
                [
                    'contentStreamId' => $config->getStringValue(),
                    'exception' => $exception,
                ]
            );

            return null;
        }

        // Exclude unavailable closeout blogs before the limit is applied, so they
        // do not consume slider slots (same handling as listing and cross-selling).
        $context = $resolverContext->getChannelContext();
        if ($this->hideUnavailableBlogs($context)) {
            $criteria->addFilter($this->blogCloseoutFilterFactory->create($context));
        }

        $criteria->setLimit($limit);

        if (!$this->shouldSkipGrouping($criteria)) {
            $this->addGrouping($criteria);
        }
        $sorting = $elementConfig->get('contentStreamSorting')?->getStringValue() ?? 'name:' . FieldSorting::ASCENDING;

        if ($sorting === 'random') {
            $this->addRandomSort($criteria);
        } else {
            $sorting = explode(':', $sorting);
            $field = $sorting[0];
            $direction = $sorting[1];

            $criteria->addSorting(new FieldSorting($field, $direction));
        }

        return $criteria;
    }

    private function hideUnavailableBlogs(ChannelContext $context): bool
    {
        return $this->systemConfigService->getBool(
            'core.listing.hideCloseoutBlogsWhenOutOfStock',
            $context->getChannelId()
        );
    }

    private function handleBlogStream(
        BlogCollection $streamResult,
        ChannelContext $context,
        Criteria $originCriteria
    ): BlogCollection {
        if ($this->shouldSkipGrouping($originCriteria)) {
            return $streamResult;
        }

        $finalBlogIds = $this->collectFinalBlogIds($streamResult);
        if ($finalBlogIds === []) {
            return new BlogCollection();
        }

        $criteria = $originCriteria->cloneForRead($finalBlogIds);

        $blogs = $this->blogRepository->search($criteria, $context)->getEntities();
        $blogs->sortByIdArray($finalBlogIds);

        return $blogs;
    }

    /**
     * @return list<string>
     */
    private function collectFinalBlogIds(BlogCollection $streamResult): array
    {
        $finalBlogIds = [];
        foreach ($streamResult as $blog) {
            $variantConfig = $blog->getVariantListingConfig();

            if (!$variantConfig) {
                $finalBlogIds[] = $blog->getId();
                continue;
            }

            $blogId = $variantConfig->getDisplayParent()
                ? $blog->getParentId() : $variantConfig->getMainVariantId();

            $finalBlogIds[] = $blogId ?? $blog->getId();
        }

        return array_values(array_unique($finalBlogIds));
    }

    private function addGrouping(Criteria $criteria): void
    {
        $criteria->addGroupField(new FieldGrouping('displayGroup'));
        $criteria->addFilter(new NotEqualsFilter('displayGroup', null));
    }

    private function addRandomSort(Criteria $criteria): void
    {
        // these fields should be compatible with Elasticsearch mapped fields for sorting, see: \Contena\Elasticsearch\Blog\ElasticsearchBlogDefinition::getMapping
        $fields = [
            'id',
            'stock',
            'releaseDate',
            'manufacturerId',
            'deliveryTimeId',
            'taxId',
            'coverId',
        ];
        shuffle($fields);
        $fields = \array_slice($fields, 0, 2);
        $direction = [FieldSorting::ASCENDING, FieldSorting::DESCENDING];
        $direction = $direction[random_int(0, 1)];

        foreach ($fields as $field) {
            $criteria->addSorting(new FieldSorting($field, $direction));
        }
    }

    private function shouldSkipGrouping(Criteria $criteria): bool
    {
        return $criteria->hasState(BlogListingLoader::STATE_SKIP_ADD_GROUPING);
    }
}
