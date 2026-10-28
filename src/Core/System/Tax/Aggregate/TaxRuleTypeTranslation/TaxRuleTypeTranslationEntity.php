<?php declare(strict_types=1);

namespace Contena\Core\System\Tax\Aggregate\TaxRuleTypeTranslation;

use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class TaxRuleTypeTranslationEntity extends TranslationEntity
{
    protected string $taxRuleTypeId;

    protected ?string $typeName = null;

    protected ?TaxRuleTypeEntity $taxRuleType = null;

    public function getTaxRuleTypeId(): string
    {
        return $this->taxRuleTypeId;
    }

    public function setTaxRuleTypeId(string $taxRuleTypeId): void
    {
        $this->taxRuleTypeId = $taxRuleTypeId;
    }

    public function getTypeName(): ?string
    {
        return $this->typeName;
    }

    public function setTypeName(?string $typeName): void
    {
        $this->typeName = $typeName;
    }

    public function getTaxRuleType(): ?TaxRuleTypeEntity
    {
        return $this->taxRuleType;
    }

    public function setTaxRuleType(?TaxRuleTypeEntity $taxRuleType): void
    {
        $this->taxRuleType = $taxRuleType;
    }
}
