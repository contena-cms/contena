<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\PurchaseLimit;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @codeCoverageIgnore
 *
 * @extends Collection<ProductPurchaseLimit>
 */
#[Package('inventory')]
class ProductPurchaseLimitCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return ProductPurchaseLimit::class;
    }
}
