<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Aggregate\ProductSearchKeyword;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductSearchKeywordEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductSearchKeywordCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_search_keyword_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductSearchKeywordEntity::class;
    }
}
