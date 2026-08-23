<?php declare(strict_types=1);

namespace Contena\Core\System\Organization\Aggregate\OrganizationTranslation;

use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\System\Organization\OrganizationEntity;

class OrganizationTranslationEntity extends TranslationEntity
{
    protected ?string $tenantId = null;

    protected string $organizationId;

    protected ?string $name = null;

    protected ?string $shortName = null;

    protected ?OrganizationEntity $organization = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function setOrganizationId(string $organizationId): void
    {
        $this->organizationId = $organizationId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): void
    {
        $this->shortName = $shortName;
    }

    public function getOrganization(): ?OrganizationEntity
    {
        return $this->organization;
    }

    public function setOrganization(OrganizationEntity $organization): void
    {
        $this->organization = $organization;
    }
}
