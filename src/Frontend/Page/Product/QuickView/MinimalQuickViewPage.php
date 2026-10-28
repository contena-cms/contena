<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Product\QuickView;

use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

#[Package('discovery')]
class MinimalQuickViewPage extends Struct
{
    /**
     * @internal
     */
    public function __construct(
        protected ProductEntity $product,
    ) {
    }

    public function getProduct(): ProductEntity
    {
        return $this->product;
    }
}
