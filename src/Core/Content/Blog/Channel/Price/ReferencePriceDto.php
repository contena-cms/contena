<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Price;

use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @final
 */
class ReferencePriceDto extends Struct
{
    public function __construct(
        protected ?float $purchase,
        protected ?float $reference,
        protected ?string $unitId
    ) {
    }

    public static function createFromEntity(Entity $blog): ReferencePriceDto
    {
        return new self(
            $blog->get('purchaseUnit'),
            $blog->get('referenceUnit'),
            $blog->get('unitId')
        );
    }

    public static function createFromCheapestPrice(CheapestPrice $price): ReferencePriceDto
    {
        return new ReferencePriceDto($price->getPurchase(), $price->getReference(), $price->getUnitId());
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
