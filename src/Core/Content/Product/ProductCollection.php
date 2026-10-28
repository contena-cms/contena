<?php declare(strict_types=1);

namespace Contena\Core\Content\Product;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @template TElement of ProductEntity = ProductEntity
 *
 * @extends EntityCollection<TElement>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_collection';
    }

    /**
     * @return class-string<ProductEntity>
     */
    protected function getExpectedClass(): string
    {
        return ProductEntity::class;
    }
}
