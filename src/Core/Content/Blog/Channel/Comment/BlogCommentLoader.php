<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Comment;

use Contena\Core\Content\Blog\Channel\Comment\Event\BlogCommentsLoadedEvent;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class BlogCommentLoader extends AbstractBlogCommentLoader
{
    private const string PARAMETER_NAME_PAGE = 'p';
    private const string PARAMETER_NAME_LANGUAGE = 'language';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractBlogCommentRoute $blogCommentRoute,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getDecorated(): AbstractBlogCommentLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(Request $request, ChannelContext $context, string $blogId): BlogCommentResult
    {
        $criteria = $this->createCriteria($request, $context);
        $comments = $this->blogCommentRoute->load($blogId, $request, $context, $criteria)->getResult();
        $result = new BlogCommentResult($comments, $blogId);

        $this->eventDispatcher->dispatch(new BlogCommentsLoadedEvent($result, $request, $context));

        return $result;
    }

    private function createCriteria(Request $request, ChannelContext $context): Criteria
    {
        $limit = $this->systemConfigService->getInt('core.listing.commentsPerPage', $context->getChannelId());
        $page = (int) RequestParamHelper::get($request, self::PARAMETER_NAME_PAGE, 1);

        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset(max(0, $limit * ($page - 1)));
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        if (RequestParamHelper::get($request, self::PARAMETER_NAME_LANGUAGE) === 'filter-language') {
            $criteria->addPostFilter(new EqualsFilter('languageId', $context->getLanguageId()));
        }

        return $criteria;
    }
}
