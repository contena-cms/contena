<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\SearchKeyword;

use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

class BlogSearchBuilder implements BlogSearchBuilderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BlogSearchTermInterpreterInterface $interpreter,
        private readonly LoggerInterface $logger,
        private readonly int $searchTermMaxLength,
        private readonly bool $searchKeywordIndexingEnabled = true,
    ) {
    }

    public function build(Request $request, Criteria $criteria, ChannelContext $context): void
    {
        $search = RequestParamHelper::get($request, 'search');

        if (\is_array($search)) {
            $term = implode(' ', $search);
        } else {
            $term = (string) $search;
        }

        $term = trim($term);
        if (mb_strlen($term) > $this->searchTermMaxLength) {
            $this->logger->notice(
                'The search term "{term}" was trimmed because it exceeded the maximum length of {maxLength} characters.',
                ['term' => $term, 'maxLength' => $this->searchTermMaxLength]
            );

            $term = mb_substr($term, 0, $this->searchTermMaxLength);
        }

        if ($term === '') {
            throw BlogException::missingRequestParameter('search');
        }

        if (!$this->searchKeywordIndexingEnabled) {
            $criteria->setTerm($term);

            return;
        }

        $pattern = $this->interpreter->interpret($term, $context->getContext());

        foreach ($pattern->getTerms() as $searchTerm) {
            $criteria->addQuery(
                new ScoreQuery(
                    new EqualsFilter('blog.searchKeywords.keyword', $searchTerm->getTerm()),
                    $searchTerm->getScore(),
                    'blog.searchKeywords.ranking'
                )
            );
        }
        $criteria->addQuery(
            new ScoreQuery(
                new ContainsFilter('blog.searchKeywords.keyword', $pattern->getOriginal()->getTerm()),
                $pattern->getOriginal()->getScore(),
                'blog.searchKeywords.ranking'
            )
        );

        if ($pattern->getBooleanClause() !== SearchPattern::BOOLEAN_CLAUSE_AND) {
            $criteria->addFilter(new AndFilter([
                new EqualsAnyFilter('blog.searchKeywords.keyword', array_values($pattern->getAllTerms())),
                new EqualsFilter('blog.searchKeywords.languageId', $context->getLanguageId()),
            ]));

            return;
        }

        foreach ($pattern->getTokenTerms() as $terms) {
            $criteria->addFilter(new AndFilter([
                new EqualsFilter('blog.searchKeywords.languageId', $context->getLanguageId()),
                new EqualsAnyFilter('blog.searchKeywords.keyword', $terms),
            ]));
        }
    }
}
