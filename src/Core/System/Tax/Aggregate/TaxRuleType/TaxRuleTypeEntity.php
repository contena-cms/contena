<?php declare(strict_types=1);

namespace Contena\Core\System\Tax\Aggregate\TaxRuleType;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\System\Tax\Aggregate\TaxRuleTypeTranslation\TaxRuleTypeTranslationCollection;

/**
 * @codeCoverageIgnore
 */
class TaxRuleTypeEntity extends Entity
{
    use EntityIdTrait;
use Contena\Core\System\Tax\Aggregate\TaxRule\TaxRuleCollection;

    protected string $typeName;

    protected string $technicalName;

    protected int $position;

    protected ?TaxRuleCollection $rules = null;

    protected ?TaxRuleTypeTranslationCollection $translations = null;

    public function getTypeName(): string
    {
        return $this->typeName;
    }

    public function setTypeName(string $typeName): void
    {
        $this->typeName = $typeName;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getRules(): ?TaxRuleCollection
    {
        return $this->rules;
    }

    public function setRules(TaxRuleCollection $rules): void
    {
        $this->rules = $rules;
    }

    public function getTranslations(): ?TaxRuleTypeTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(TaxRuleTypeTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
