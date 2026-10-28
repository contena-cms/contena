<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Stock;

use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
class StockLoadRequest
{
    /**
     * @param array<string> $productIds
     */
    public function __construct(public array $productIds)
    {
    }
}
