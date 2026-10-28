<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SearchKeyword;

use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
interface ProductSearchKeywordAnalyzerInterface
{
    /**
     * @param array<int, array{field: string, tokenize: '1'|'0'|bool, ranking: numeric-string|int|float, language_id?: string}> $configFields
     */
    public function analyze(ProductEntity $product, Context $context, array $configFields): AnalyzedKeywordCollection;
}
