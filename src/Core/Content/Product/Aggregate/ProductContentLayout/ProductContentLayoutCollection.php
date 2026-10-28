<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Aggregate\ProductContentLayout;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<ProductContentLayoutEntity>
 */
#[Package('inventory')]
class ProductContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductContentLayoutEntity::class;
    }
}
