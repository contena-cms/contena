<?php declare(strict_types=1);

namespace Contena\Core\System\Organization;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Organization\Aggregate\OrganizationTranslation\OrganizationTranslationCollection;
use Contena\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitEntity;

class OrganizationEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected ?string $parentId = null;

    protected int $level;

    protected ?string $path = null;

    protected int $childCount;

    protected string $code;

    protected string $organizationUnitId;

    protected ?string $name = null;

    protected ?string $shortName = null;

    protected int $position;

    protected bool $active;

    protected ?OrganizationUnitEntity $organizationUnit = null;

    protected ?OrganizationEntity $parent = null;

    protected ?OrganizationCollection $children = null;

    protected ?OrganizationTranslationCollection $translations = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    public function getChildCount(): int
    {
        return $this->childCount;
    }

    public function setChildCount(int $childCount): void
    {
        $this->childCount = $childCount;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
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

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): void
    {
        $this->shortName = $shortName;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getOrganizationUnit(): ?OrganizationUnitEntity
    {
        return $this->organizationUnit;
    }

    public function setOrganizationUnit(OrganizationUnitEntity $organizationUnit): void
    {
        $this->organizationUnit = $organizationUnit;
    }

    public function getParent(): ?OrganizationEntity
    {
        return $this->parent;
    }

    public function setParent(?OrganizationEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?OrganizationCollection
    {
        return $this->children;
    }

    public function setChildren(OrganizationCollection $children): void
    {
        $this->children = $children;
    }

    public function getTranslations(): ?OrganizationTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(OrganizationTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
