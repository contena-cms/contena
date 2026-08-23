<?php declare(strict_types=1);

namespace Contena\Core\System\Organization\Aggregate\OrganizationUnit;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Organization\Aggregate\OrganizationUnitTranslation\OrganizationUnitTranslationCollection;
use Contena\Core\System\Organization\OrganizationCollection;

class OrganizationUnitEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $technicalName;

    protected ?string $name = null;

    protected ?string $description = null;

    protected int $position;

    protected bool $active;

    protected ?OrganizationCollection $organizations = null;

    protected ?OrganizationUnitTranslationCollection $translations = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getOrganizations(): ?OrganizationCollection
    {
        return $this->organizations;
    }

    public function setOrganizations(OrganizationCollection $organizations): void
    {
        $this->organizations = $organizations;
    }

    public function getTranslations(): ?OrganizationUnitTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(OrganizationUnitTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
