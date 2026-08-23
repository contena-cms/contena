<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelType;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Channel\Aggregate\ChannelTypeTranslation\ChannelTypeTranslationCollection;
use Contena\Core\System\Channel\ChannelCollection;

class ChannelTypeEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $name = null;

    protected ?string $manufacturer = null;

    protected ?string $description = null;

    protected ?string $descriptionLong = null;

    protected ?string $coverUrl = null;

    protected ?string $iconName = null;

    /**
     * @var list<string>|null
     */
    protected ?array $screenshotUrls = null;

    protected ?ChannelCollection $channels = null;

    protected ?ChannelTypeTranslationCollection $translations = null;

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

    public function getCoverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function setCoverUrl(?string $coverUrl): void
    {
        $this->coverUrl = $coverUrl;
    }

    public function getIconName(): ?string
    {
        return $this->iconName;
    }

    public function setIconName(?string $iconName): void
    {
        $this->iconName = $iconName;
    }

    /**
     * @return list<string>|null
     */
    public function getScreenshotUrls(): ?array
    {
        return $this->screenshotUrls;
    }

    /**
     * @param list<string>|null $screenshotUrls
     */
    public function setScreenshotUrls(?array $screenshotUrls): void
    {
        $this->screenshotUrls = $screenshotUrls;
    }

    public function getChannels(): ?ChannelCollection
    {
        return $this->channels;
    }

    public function setChannels(ChannelCollection $channels): void
    {
        $this->channels = $channels;
    }

    public function getTranslations(): ?ChannelTypeTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ChannelTypeTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
