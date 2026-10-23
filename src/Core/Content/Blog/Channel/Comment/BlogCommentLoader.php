<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Comment;

use Contena\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Contena\Core\Content\Blog\Aggregate\BlogComment\BlogCommentEntity;
use Contena\Core\Content\Blog\Channel\Comment\Event\BlogCommentsLoadedEvent;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class BlogCommentLoader extends AbstractBlogCommentLoader
{
    private const PARAMETER_NAME_PAGE = 'p';
    private const PARAMETER_NAME_SORT = 'sort';
    private const PARAMETER_NAME_LANGUAGE = 'language';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractBlogCommentRoute $blogCommentRoute,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getDecorated(): AbstractBlogCommentLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(
        Request $request,
        ChannelContext $context,
        string $blogId,
        ?string $blogParentId = null
    ): BlogCommentResult {
        $commentCriteria = $this->createCommentCriteria($request, $context);
        $comments = $this->blogCommentRoute
            ->load($blogParentId ?? $blogId, $request, $context, $commentCriteria)
            ->getResult();

        $commentResult = BlogCommentResult::fromSearchResult(
            $comments,
            $blogId,
            $this->getTotalCommentsInCurrentLanguage($comments),
            $this->getUserComment($blogId, $context),
            $blogParentId ?? $blogId,
        );

        $this->eventDispatcher->dispatch(new BlogCommentsLoadedEvent($commentResult, $request, $context));

        return $commentResult;
    }

    /**
     * @param EntitySearchResult<BlogCommentCollection> $comments
     */
    private function getTotalCommentsInCurrentLanguage(EntitySearchResult $comments): int
    {
        $aggregation = $comments->getAggregations()->get('languageMatrix');

        if ($aggregation instanceof TermsResult) {
            $buckets = $aggregation->getBuckets();

            return $buckets === [] ? 0 : $buckets[0]->getCount();
        }

        return $comments->getTotal();
    }

    private function createCommentCriteria(Request $request, ChannelContext $context): Criteria
    {
        $limit = $this->systemConfigService->getInt('core.listing.commentsPerPage', $context->getChannelId());
        $page = (int) RequestParamHelper::get($request, self::PARAMETER_NAME_PAGE, 1);
        $offset = max(0, $limit * ($page - 1));

        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset($offset);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $criteria->addSorting(new FieldSorting('createdAt', 'DESC'));

        if (RequestParamHelper::get($request, self::PARAMETER_NAME_LANGUAGE) === 'filter-language') {
            $criteria->addPostFilter(
                new EqualsFilter('languageId', $context->getLanguageId())
            );
        } else {
            $criteria->addAssociation('language.translationCode.code');
        }

        $this->handleCommentAggregation($criteria, $context);

        return $criteria;
    }

    private function getUserComment(string $blogId, ChannelContext $context): ?BlogCommentEntity
    {
        $user = $context->getUser();

        if (!$user) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->setOffset(0);
        $criteria->addFilter(new EqualsFilter('userId', $user->getId()));

        $userComments = $this->blogCommentRoute
            ->load($blogId, new Request(), $context, $criteria)
            ->getResult()
            ->getEntities();

        return $userComments->first();
    }

    private function handleCommentAggregation(Criteria $criteria, ChannelContext $context): void
    {
        $criteria->addAggregation(
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
