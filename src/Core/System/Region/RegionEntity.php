<?php declare(strict_types=1);

namespace Contena\Core\System\Region;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Region\Aggregate\RegionTranslation\RegionTranslationCollection;

class RegionEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $countryId;

    protected ?string $parentId = null;

    protected int $level;

    protected string $type;

    protected ?string $code = null;

    protected ?string $path = null;

    protected int $childCount;

    protected ?string $name = null;

    protected ?string $shortName = null;

    protected int $position;

    protected bool $active;

    protected ?CountryEntity $country = null;

    protected ?RegionEntity $parent = null;

    protected ?RegionCollection $children = null;

    protected ?RegionTranslationCollection $translations = null;

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function setCountryId(string $countryId): void
    {
        $this->countryId = $countryId;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getChildCount(): int
    {
        return $this->childCount;
    }

    public function setChildCount(int $childCount): void
    {
        $this->childCount = $childCount;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
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

    public function getCountry(): ?CountryEntity
    {
        return $this->country;
    }

    public function setCountry(CountryEntity $country): void
    {
        $this->country = $country;
    }

    public function getParent(): ?RegionEntity
    {
        return $this->parent;
    }

    public function setParent(?RegionEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?RegionCollection
    {
        return $this->children;
    }

    public function setChildren(RegionCollection $children): void
    {
        $this->children = $children;
    }

    public function getTranslations(): ?RegionTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(RegionTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
