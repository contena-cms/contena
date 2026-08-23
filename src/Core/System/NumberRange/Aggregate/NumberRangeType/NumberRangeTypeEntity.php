<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\Aggregate\NumberRangeType;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation\NumberRangeTypeTranslationCollection;
use Contena\Core\System\NumberRange\NumberRangeCollection;

class NumberRangeTypeEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $typeName;

    protected string $technicalName;

    protected bool $global;

    protected ?NumberRangeCollection $numberRanges = null;

    protected ?NumberRangeTypeTranslationCollection $translations = null;

    public function getTypeName(): string
    {
        return $this->typeName;
    }

    public function setTypeName(string $typeName): void
    {
        $this->typeName = $typeName;
    }

    public function getGlobal(): bool
    {
        return $this->global;
    }

    public function setGlobal(bool $global): void
    {
        $this->global = $global;
    }

    public function getNumberRanges(): ?NumberRangeCollection
    {
        return $this->numberRanges;
    }

    public function setNumberRanges(NumberRangeCollection $numberRanges): void
    {
        $this->numberRanges = $numberRanges;
    }

    public function getTranslations(): ?NumberRangeTypeTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(NumberRangeTypeTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }
}
