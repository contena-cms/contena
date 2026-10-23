<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Stock;

use Contena\Core\Framework\Log\Package;

final readonly class StockAlteration
{
    public function __construct(
        public string $lineItemId,
        public string $blogId,
        public int $quantityBefore,
        public int $newQuantity
    ) {
    }

    public function quantityDelta(): int
    {
        return $this->quantityBefore - $this->newQuantity;
    }
}
