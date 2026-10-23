<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\SearchKeyword;

use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\Context;

interface BlogSearchKeywordAnalyzerInterface
{
    /**
     * @param array<int, array{field: string, tokenize: '1'|'0'|bool, ranking: numeric-string|int|float, language_id?: string}> $configFields
     */
    public function analyze(BlogEntity $blog, Context $context, array $configFields): AnalyzedKeywordCollection;
}
