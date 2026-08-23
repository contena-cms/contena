<?php declare(strict_types=1);

namespace Contena\Core\System\Country;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationCollection;
use Contena\Core\System\Region\RegionCollection;

class CountryEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $name = null;

    protected ?string $iso = null;

    protected int $position;

    protected bool $active;

    protected ?string $iso3 = null;

    protected ?RegionCollection $regions = null;

    protected ?CountryTranslationCollection $translations = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getIso(): ?string
    {
        return $this->iso;
    }

    public function setIso(?string $iso): void
    {
        $this->iso = $iso;
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

    public function getIso3(): ?string
    {
        return $this->iso3;
    }

    public function setIso3(?string $iso3): void
    {
        $this->iso3 = $iso3;
    }

    public function getRegions(): ?RegionCollection
    {
        return $this->regions;
    }

    public function setRegions(RegionCollection $regions): void
    {
        $this->regions = $regions;
    }

    public function getTranslations(): ?CountryTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(CountryTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
