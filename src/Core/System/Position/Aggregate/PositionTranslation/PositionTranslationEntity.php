<?php declare(strict_types=1);

namespace Contena\Core\System\Position\Aggregate\PositionTranslation;

use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\System\Position\PositionEntity;

class PositionTranslationEntity extends TranslationEntity
{
    protected ?string $tenantId = null;

    protected string $positionId;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?PositionEntity $position = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getPositionId(): string
    {
        return $this->positionId;
    }

    public function setPositionId(string $positionId): void
    {
        $this->positionId = $positionId;
    }

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

    public function getPosition(): ?PositionEntity
    {
        return $this->position;
    }

    public function setPosition(PositionEntity $position): void
    {
        $this->position = $position;
    }
}
