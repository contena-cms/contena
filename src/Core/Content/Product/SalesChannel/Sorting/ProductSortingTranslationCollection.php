<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Sorting;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductSortingTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductSortingTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_sorting_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductSortingTranslationEntity::class;
    }
}
