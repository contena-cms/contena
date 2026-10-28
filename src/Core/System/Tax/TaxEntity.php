<?php declare(strict_types=1);

namespace Contena\Core\System\Tax;

use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Tax\Aggregate\TaxRule\TaxRuleCollection;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class TaxEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected float $taxRate;

    protected string $name;

    protected int $position;

    protected ?ProductCollection $products = null;

    protected ?TaxRuleCollection $rules = null;


    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function setTaxRate(float $taxRate): void
    {
        $this->taxRate = $taxRate;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }

    public function getRules(): ?TaxRuleCollection
    {
        return $this->rules;
    }

    public function setRules(TaxRuleCollection $rules): void
    {
        $this->rules = $rules;
    }


}
