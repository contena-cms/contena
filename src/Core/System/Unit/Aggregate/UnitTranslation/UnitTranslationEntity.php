<?php declare(strict_types=1);

namespace Contena\Core\System\Unit\Aggregate\UnitTranslation;

use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;

/**
 * @codeCoverageIgnore
 */
class UnitTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;
use Contena\Core\System\Unit\UnitEntity;

    protected string $unitId;

    protected ?string $shortCode = null;

    protected ?string $name = null;

    protected ?UnitEntity $unit = null;

    public function getUnitId(): string
    {
        return $this->unitId;
    }

    public function setUnitId(string $unitId): void
    {
        $this->unitId = $unitId;
    }

    public function getShortCode(): ?string
    {
        return $this->shortCode;
    }

    public function setShortCode(?string $shortCode): void
    {
        $this->shortCode = $shortCode;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getUnit(): ?UnitEntity
    {
        return $this->unit;
    }

    public function setUnit(UnitEntity $unit): void
    {
        $this->unit = $unit;
    }
}
