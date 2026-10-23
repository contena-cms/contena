<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Blog;

use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaCollection;
use Contena\Core\Content\Blog\Channel\Detail\AbstractBlogDetailRoute;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewResult;
use Contena\Core\Content\Blog\Channel\Review\RatingMatrix;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
class BlogPageLoader
{
    /**
     * Maximum number of individual Review items to include in the JSON-LD output.
     *
     * Reviews are sorted by date descending (most recent first) so the sample is
     * chronologically neutral. Do NOT change the sort to points/rating — cherry-picking
     * high-rated reviews while the ratingCount reflects a lower average violates
     * review markup spam policy.
     */
    private const MAX_REVIEWS_IN_JSON_LD = 10;

    /**
     * @internal
     *
     * @param EntityRepository<BlogReviewCollection> $blogReviewRepository
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractBlogDetailRoute $blogDetailRoute,
        private readonly EntityRepository $blogReviewRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly CategoryBreadcrumbBuilder $breadcrumbBuilder
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     * @throws BlogNotFoundException
     */
    public function load(Request $request, ChannelContext $context): BlogPage
    {
        $blogId = $request->attributes->get('blogId');
        if (!$blogId) {
            throw RoutingException::missingRequestParameter('blogId', '/blogId');
        }

        $criteria = (new Criteria())
            ->addAssociation('manufacturer.media')
            ->addAssociation('options.group')
            ->addAssociation('properties.group')
            ->addAssociation('mainCategories.category')
            ->addAssociation('media.media')
            ->addAssociation('openGraphMedia')
            ->addAssociation('guaranteeTermsMedia');

        $criteria->getAssociation('media')->addSorting(
            new FieldSorting('position')
        );

        $this->eventDispatcher->dispatch(new BlogPageCriteriaEvent($blogId, $criteria, $context));

        $result = $this->blogDetailRoute->load($blogId, $request, $context, $criteria);
        $blog = $result->getBlog();

        if ($blog->getMedia() && $blog->getCover()) {
            $blog->setMedia(new BlogMediaCollection(array_merge(
                [$blog->getCover()->getId() => $blog->getCover()],
                $blog->getMedia()->getElements()
            )));
        }

        $page = $this->genericLoader->load($request, $context);
        $page = BlogPage::createFrom($page);

        $page->setBlog($blog);
        $page->setConfiguratorSettings($result->getConfigurator() ?? new PropertyGroupCollection());
        $page->setNavigationId($blog->getId());

        if ($category = $blog->getSeoCategory()) {
            $request->request->set('navigationId', $category->getId());

            if (Feature::isActive('BREADCRUMB_REWORK')) {
                $page->setBreadcrumb($this->breadcrumbBuilder->getCategoryBreadcrumbUrls($category, $context->getContext(), $context->getChannel()));
            }
        }

        if ($cmsPage = $blog->getCmsPage()) {
            $page->setCmsPage($cmsPage);
        }

        $this->loadOptions($page);
        $this->loadMetaData($page);

        if (Feature::isActive('JSON_LD_DATA')) {
            $this->loadStructuredDataReviews($page, $context);
        }

        $this->eventDispatcher->dispatch(
            new BlogPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function loadOptions(BlogPage $page): void
    {
        $options = new PropertyGroupOptionCollection();

        if (($optionIds = $page->getBlog()->getOptionIds()) === null) {
            $page->setSelectedOptions($options);

            return;
        }

        foreach ($page->getConfiguratorSettings() as $group) {
            $groupOptions = $group->getOptions();
            if ($groupOptions === null) {
                continue;
            }
            foreach ($optionIds as $optionId) {
                $groupOption = $groupOptions->get($optionId);
                if ($groupOption !== null) {
                    $options->add($groupOption);
                }
            }
        }

        $page->setSelectedOptions($options);
    }

    private function loadMetaData(BlogPage $page): void
    {
        $metaInformation = $page->getMetaInformation();

        if (!$metaInformation) {
            return;
        }

        $metaDescription = $page->getBlog()->getTranslation('metaDescription')
            ?? $page->getBlog()->getTranslation('description');
        $metaInformation->setMetaDescription((string) $metaDescription);

        $metaInformation->setMetaKeywords((string) $page->getBlog()->getTranslation('keywords'));

        if ((string) $page->getBlog()->getTranslation('metaTitle') !== '') {
            $metaInformation->setMetaTitle((string) $page->getBlog()->getTranslation('metaTitle'));

            return;
        }

        $metaTitleParts = [$page->getBlog()->getTranslation('name')];

        foreach ($page->getSelectedOptions() as $option) {
            $metaTitleParts[] = $option->getTranslation('name');
        }

        $metaTitleParts[] = $page->getBlog()->getBlogNumber();

        $metaInformation->setMetaTitle(implode(' | ', $metaTitleParts));
    }

    /**
     * Loads a small sample of approved reviews and the total approved review count directly
     * from the repository for use in the JSON-LD Blog schema (AggregateRating + Review items).
     *
     * The result is stored on BlogPage::$structuredDataReviews. It intentionally contains
     * at most MAX_REVIEWS_IN_JSON_LD items and must NOT be used to render a review list.
     */
    private function loadStructuredDataReviews(BlogPage $page, ChannelContext $context): void
    {
        // Don't add reviews to the structured data if reviews are disabled.
        if (!$this->systemConfigService->getBool('core.listing.showReview', $context->getChannelId())) {
            return;
        }

        $blog = $page->getBlog();
        $blogId = $blog->getParentId() ?? $blog->getId();

        $criteria = new Criteria();
        $criteria->setLimit(self::MAX_REVIEWS_IN_JSON_LD);
        $criteria->setOffset(0);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        // Most recent first — neutral, unbiased sample (see MAX_REVIEWS_IN_JSON_LD comment).
        // Ratings are not allowed to be sorted by points/rating.
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->addAssociation('language.translationCode');

        // Only approved reviews; also scope to this blog (or its parent for variants).
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('status', true),
            new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('blog.id', $blogId),
                new EqualsFilter('blog.parentId', $blogId),
            ]),
        ]));

        $criteria->addAggregation(
            new FilterAggregation(
                'json-ld-status-filter',
                new TermsAggregation('ratingMatrix', 'points'),
                [new EqualsFilter('status', true)]
            )
        );

        $entityResult = $this->blogReviewRepository->search($criteria, $context->getContext());

        $aggregation = $entityResult->getAggregations()->get('ratingMatrix');
        $matrix = new RatingMatrix($aggregation instanceof TermsResult ? $aggregation->getBuckets() : []);

        $reviewResult = BlogReviewResult::fromSearchResult(
            $entityResult,
            $matrix,
            $blogId,
            $entityResult->getTotal(),
        );

        $page->setStructuredDataReviews($reviewResult);
    }
}
