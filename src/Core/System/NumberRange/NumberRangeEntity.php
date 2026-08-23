<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeState\NumberRangeStateEntity;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeTranslation\NumberRangeTranslationCollection;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeEntity;

class NumberRangeEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $typeId = null;

    protected bool $global;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?string $pattern = null;

    protected ?int $start = null;

    protected ?NumberRangeTypeEntity $type = null;

    protected ?NumberRangeStateEntity $state = null;

    protected ?NumberRangeTranslationCollection $translations = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function setPattern(?string $pattern): void
    {
        $this->pattern = $pattern;
    }

    public function getStart(): ?int
    {
        return $this->start;
    }

    public function setStart(?int $start): void
    {
        $this->start = $start;
    }

    public function getType(): ?NumberRangeTypeEntity
    {
        return $this->type;
    }

    public function setType(?NumberRangeTypeEntity $type): void
    {
        $this->type = $type;
    }

    public function getState(): ?NumberRangeStateEntity
    {
        return $this->state;
    }

    public function setState(?NumberRangeStateEntity $state): void
    {
        $this->state = $state;
    }

    public function getTypeId(): ?string
    {
        return $this->typeId;
    }

    public function setTypeId(?string $typeId): void
    {
        $this->typeId = $typeId;
    }

    public function isGlobal(): bool
    {
        return $this->global;
    }

    public function setGlobal(bool $global): void
    {
        $this->global = $global;
    }

    public function getTranslations(): ?NumberRangeTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(NumberRangeTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
