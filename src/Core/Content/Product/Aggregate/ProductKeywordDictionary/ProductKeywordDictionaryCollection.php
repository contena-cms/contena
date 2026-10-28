<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Aggregate\ProductKeywordDictionary;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductKeywordDictionaryEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductKeywordDictionaryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_keyword_dictionary_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductKeywordDictionaryEntity::class;
    }
}
