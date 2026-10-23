<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice;

use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

class CheapestPrice extends Struct
{
    protected bool $hasRange;

    protected string $variantId;

    protected string $parentId;

    protected ?string $ruleId = null;

    protected ?float $purchase = null;

    protected ?float $reference = null;

    protected ?string $unitId = null;

    public function getCurrencyPrice(string $currencyId): ?Price
    {
        return $this->price->getCurrencyPrice($currencyId);
    }

    public function getVariantId(): string
    {
        return $this->variantId;
    }

    public function setVariantId(string $variantId): void
    {
        $this->variantId = $variantId;
    }

    public function getRuleId(): ?string
    {
        return $this->ruleId;
    }

    public function setRuleId(?string $ruleId): void
    {
        $this->ruleId = $ruleId;
    }

    public function getPrice(): PriceCollection
    {
        return $this->price;
    }

    public function setPrice(PriceCollection $price): void
    {
        $this->price = $price;
    }

    public function hasRange(): bool
    {
        return $this->hasRange;
    }

    public function setHasRange(bool $hasRange): void
    {
        $this->hasRange = $hasRange;
    }

    public function getParentId(): string
    {
        return $this->parentId;
    }

    public function setParentId(string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getPurchase(): ?float
    {
        return $this->purchase;
    }

    public function setPurchase(?float $purchase): void
    {
        $this->purchase = $purchase;
    }

    public function getReference(): ?float
    {
        return $this->reference;
    }

    public function setReference(?float $reference): void
    {
        $this->reference = $reference;
    }

    public function getUnitId(): ?string
    {
        return $this->unitId;
    }

    public function setUnitId(?string $unitId): void
    {
        $this->unitId = $unitId;
    }
}
