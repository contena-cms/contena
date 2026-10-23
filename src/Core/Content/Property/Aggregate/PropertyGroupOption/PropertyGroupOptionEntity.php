<?php declare(strict_types=1);

namespace Contena\Core\Content\Property\Aggregate\PropertyGroupOption;

use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting\BlogConfiguratorSettingCollection;
use Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting\BlogConfiguratorSettingEntity;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationCollection;
use Contena\Core\Content\Property\PropertyGroupEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class PropertyGroupOptionEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $groupId;

    protected ?string $name = null;

    protected ?int $position = null;

    protected ?string $colorHexCode = null;

    protected ?string $mediaId = null;

    protected ?PropertyGroupEntity $group = null;

    protected ?PropertyGroupOptionTranslationCollection $translations = null;

    protected ?BlogConfiguratorSettingCollection $blogConfiguratorSettings = null;

    protected ?BlogCollection $blogProperties = null;

    protected ?BlogCollection $blogOptions = null;

    protected ?MediaEntity $media = null;

    protected bool $combinable = false;

    /**
     * @internal
     */
    private ?BlogConfiguratorSettingEntity $configuratorSetting = null;

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function setGroupId(string $groupId): void
    {
        $this->groupId = $groupId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getColorHexCode(): ?string
    {
        return $this->colorHexCode;
    }

    public function setColorHexCode(?string $colorHexCode): void
    {
        $this->colorHexCode = $colorHexCode;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getGroup(): ?PropertyGroupEntity
    {
        return $this->group;
    }

    public function setGroup(?PropertyGroupEntity $group): void
    {
        $this->group = $group;
    }

    public function getTranslations(): ?PropertyGroupOptionTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(PropertyGroupOptionTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getBlogConfiguratorSettings(): ?BlogConfiguratorSettingCollection
    {
        return $this->blogConfiguratorSettings;
    }

    public function setBlogConfiguratorSettings(BlogConfiguratorSettingCollection $blogConfiguratorSettings): void
    {
        $this->blogConfiguratorSettings = $blogConfiguratorSettings;
    }

    public function getBlogProperties(): ?BlogCollection
    {
        return $this->blogProperties;
    }

    public function setBlogProperties(BlogCollection $blogProperties): void
    {
        $this->blogProperties = $blogProperties;
    }

    public function getBlogOptions(): ?BlogCollection
    {
        return $this->blogOptions;
    }

    public function setBlogOptions(BlogCollection $blogOptions): void
    {
        $this->blogOptions = $blogOptions;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(?MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function getCombinable(): bool
    {
        return $this->combinable;
    }

    public function setCombinable(bool $combinable): void
    {
        $this->combinable = $combinable;
    }

    public function getConfiguratorSetting(): ?BlogConfiguratorSettingEntity
    {
        return $this->configuratorSetting;
    }

    public function setConfiguratorSetting(BlogConfiguratorSettingEntity $configuratorSetting): void
    {
        $this->configuratorSetting = $configuratorSetting;
    }
}
