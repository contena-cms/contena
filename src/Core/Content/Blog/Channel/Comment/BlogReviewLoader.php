<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Review;

use Contena\Core\Content\Blog\Channel\Review\Event\BlogReviewsLoadedEvent;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

class BlogReviewLoader extends AbstractBlogReviewLoader
{
    private const PARAMETER_NAME_PAGE = 'p';
    private const PARAMETER_NAME_SORT = 'sort';
    private const PARAMETER_NAME_LANGUAGE = 'language';
    private const PARAMETER_NAME_POINTS = 'points';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractBlogReviewRoute $blogReviewRoute,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getDecorated(): AbstractBlogReviewLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(
        Request $request,
        ChannelContext $context,
        string $blogId,
        ?string $blogParentId = null
    ): BlogReviewResult {
        $reviewCriteria = $this->createReviewCriteria($request, $context);
        $reviews = $this->blogReviewRoute
            ->load($blogParentId ?? $blogId, $request, $context, $reviewCriteria)
            ->getResult();

        $reviewResult = BlogReviewResult::fromSearchResult(
            $reviews,
            $this->getReviewRatingMatrix($reviews),
            $blogId,
            $this->getTotalReviewsInCurrentLanguage($reviews),
            $this->getUserReview($blogId, $context),
            $blogParentId ?? $blogId,
        );

        $this->eventDispatcher->dispatch(new BlogReviewsLoadedEvent($reviewResult, $request, $context));

        return $reviewResult;
    }

    /**
     * @param EntitySearchResult<BlogReviewCollection> $reviews
     */
    private function getReviewRatingMatrix(EntitySearchResult $reviews): RatingMatrix
    {
        $aggregation = $reviews->getAggregations()->get('ratingMatrix');

        if ($aggregation instanceof TermsResult) {
            return new RatingMatrix($aggregation->getBuckets());
        }

        return new RatingMatrix([]);
    }

    /**
     * @param EntitySearchResult<BlogReviewCollection> $reviews
     */
    private function getTotalReviewsInCurrentLanguage(EntitySearchResult $reviews): int
    {
        $aggregation = $reviews->getAggregations()->get('languageMatrix');

        if ($aggregation instanceof TermsResult) {
            $buckets = $aggregation->getBuckets();

            return $buckets === [] ? 0 : $buckets[0]->getCount();
        }

        return $reviews->getTotal();
    }

    private function createReviewCriteria(Request $request, ChannelContext $context): Criteria
    {
        $limit = $this->systemConfigService->getInt('core.listing.reviewsPerPage', $context->getChannelId());
        $page = (int) RequestParamHelper::get($request, self::PARAMETER_NAME_PAGE, 1);
        $offset = max(0, $limit * ($page - 1));

        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset($offset);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $sorting = new FieldSorting('createdAt', 'DESC');
        if (RequestParamHelper::get($request, self::PARAMETER_NAME_SORT, 'createdAt') === 'points') {
            $sorting = new FieldSorting('points', 'DESC');
        }

        $criteria->addSorting($sorting);

        if (RequestParamHelper::get($request, self::PARAMETER_NAME_LANGUAGE) === 'filter-language') {
            $criteria->addPostFilter(
                new EqualsFilter('languageId', $context->getLanguageId())
            );
        } else {
            $criteria->addAssociation('language.translationCode.code');
        }

        $this->handlePointsAggregation($request, $criteria, $context);

        return $criteria;
    }


    private function handlePointsAggregation(Request $request, Criteria $criteria, ChannelContext $context): void
    {
        $reviewFilters = [];
        $points = RequestParamHelper::get($request, self::PARAMETER_NAME_POINTS, []);

        if (\is_array($points) && $points !== []) {
            $pointFilter = [];
            foreach ($points as $point) {
                $pointFilter[] = new RangeFilter('points', [
                    'gte' => (int) $point - 0.5,
                    'lt' => (int) $point + 0.5,
                ]);
            }

            $criteria->addPostFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $pointFilter));
        }

        $reviewFilters[] = new EqualsFilter('status', true);
        if ($context->getUser()) {
            $reviewFilters[] = new EqualsFilter('userId', $context->getUserId());
        }

        $criteria->addAggregation(
            new FilterAggregation(
                'user-login-filter',
                new TermsAggregation('ratingMatrix', 'points'),
                [
                ]
            ),
            new FilterAggregation(
                'language-filter',
                new TermsAggregation('languageMatrix', 'languageId'),
                [
                    new EqualsFilter('languageId', $context->getLanguageId()),
                ]
            )
        );
    }
}
