<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice;

use Contena\Core\Framework\Log\Package;

class CalculatedCheapestPrice extends CalculatedPrice
{
    protected bool $hasRange = false;

    protected ?string $variantId = null;

    public function hasRange(): bool
    {
        return $this->hasRange;
    }

    public function setHasRange(bool $hasRange): void
    {
        $this->hasRange = $hasRange;
    }

    public function getApiAlias(): string
    {
        return 'calculated_cheapest_price';
    }

    public function setVariantId(string $variantId): void
    {
        $this->variantId = $variantId;
    }

    public function getVariantId(): ?string
    {
        return $this->variantId;
    }
}
