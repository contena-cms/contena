<?php declare(strict_types=1);

namespace Contena\Core\System\Organization\Aggregate\OrganizationUnitTranslation;

use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitEntity;

class OrganizationUnitTranslationEntity extends TranslationEntity
{
    protected ?string $tenantId = null;

    protected string $organizationUnitId;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?OrganizationUnitEntity $organizationUnit = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getOrganizationUnitId(): string
    {
        return $this->organizationUnitId;
    }

    public function setOrganizationUnitId(string $organizationUnitId): void
    {
        $this->organizationUnitId = $organizationUnitId;
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

    public function getOrganizationUnit(): ?OrganizationUnitEntity
    {
        return $this->organizationUnit;
    }

    public function setOrganizationUnit(OrganizationUnitEntity $organizationUnit): void
    {
        $this->organizationUnit = $organizationUnit;
    }
}
