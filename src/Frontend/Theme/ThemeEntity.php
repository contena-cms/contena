<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Frontend\Theme\Aggregate\ThemeTranslationCollection;

class ThemeEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $technicalName = null;

    protected string $name;

    protected string $author;

    protected ?string $description = null;

    protected ?string $previewMediaId = null;

    protected ?string $parentThemeId = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $themeJson = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $baseConfig = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $configValues = null;

    protected bool $active;

    protected ?ThemeCollection $dependentThemes = null;

    protected ?MediaEntity $previewMedia = null;

    protected ?MediaCollection $media = null;

    protected ?ThemeTranslationCollection $translations = null;

    protected ?ChannelCollection $channels = null;

    public function getTechnicalName(): ?string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(?string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): void
    {
        $this->author = $author;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPreviewMediaId(): ?string
    {
        return $this->previewMediaId;
    }

    public function setPreviewMediaId(?string $previewMediaId): void
    {
        $this->previewMediaId = $previewMediaId;
    }

    public function getParentThemeId(): ?string
    {
        return $this->parentThemeId;
    }

    public function setParentThemeId(?string $parentThemeId): void
    {
        $this->parentThemeId = $parentThemeId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getThemeJson(): ?array
    {
        return $this->themeJson;
    }

    /**
     * @param array<string, mixed>|null $themeJson
     */
    public function setThemeJson(?array $themeJson): void
    {
        $this->themeJson = $themeJson;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBaseConfig(): ?array
    {
        return $this->baseConfig;
    }

    /**
     * @param array<string, mixed>|null $baseConfig
     */
    public function setBaseConfig(?array $baseConfig): void
    {
        $this->baseConfig = $baseConfig;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConfigValues(): ?array
    {
        return $this->configValues;
    }

    /**
     * @param array<string, mixed>|null $configValues
     */
    public function setConfigValues(?array $configValues): void
    {
        $this->configValues = $configValues;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getMedia(): ?MediaCollection
    {
        return $this->media;
    }

    public function setMedia(MediaCollection $media): void
    {
        $this->media = $media;
    }

    public function getPreviewMedia(): ?MediaEntity
    {
        return $this->previewMedia;
    }

    public function setPreviewMedia(?MediaEntity $previewMedia): void
    {
        $this->previewMedia = $previewMedia;
    }

    public function getTranslations(): ?ThemeTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ThemeTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getDependentThemes(): ?ThemeCollection
    {
        return $this->dependentThemes;
    }

    public function setDependentThemes(ThemeCollection $dependentThemes): void
    {
        $this->dependentThemes = $dependentThemes;
    }

    public function getChannels(): ?ChannelCollection
    {
        return $this->channels;
    }

    public function setChannels(ChannelCollection $channels): void
    {
        $this->channels = $channels;
    }
}
