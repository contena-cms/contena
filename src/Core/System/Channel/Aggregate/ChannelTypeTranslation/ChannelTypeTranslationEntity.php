<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelTypeTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeEntity;

class ChannelTypeTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $channelTypeId;

    protected ?string $name = null;

    protected ?string $manufacturer = null;

    protected ?string $description = null;

    protected ?string $descriptionLong = null;

    protected ?ChannelTypeEntity $channelType = null;

    public function getChannelTypeId(): string
    {
        return $this->channelTypeId;
    }

    public function setChannelTypeId(string $channelTypeId): void
    {
        $this->channelTypeId = $channelTypeId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?string $manufacturer): void
    {
        $this->manufacturer = $manufacturer;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getDescriptionLong(): ?string
    {
        return $this->descriptionLong;
    }

    public function setDescriptionLong(?string $descriptionLong): void
    {
        $this->descriptionLong = $descriptionLong;
    }

    public function getChannelType(): ?ChannelTypeEntity
    {
        return $this->channelType;
    }

    public function setChannelType(ChannelTypeEntity $channelType): void
    {
        $this->channelType = $channelType;
    }
}
