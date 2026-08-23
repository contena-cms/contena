<?php declare(strict_types=1);

namespace Contena\Core\System\Region\Aggregate\RegionTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\System\Region\RegionEntity;

class RegionTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $regionId;

    protected ?string $name = null;

    protected ?string $shortName = null;

    protected ?RegionEntity $region = null;

    public function getRegionId(): string
    {
        return $this->regionId;
    }

    public function setRegionId(string $regionId): void
    {
        $this->regionId = $regionId;
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

    public function getRegion(): ?RegionEntity
    {
        return $this->region;
    }

    public function setRegion(RegionEntity $region): void
    {
        $this->region = $region;
    }
}
