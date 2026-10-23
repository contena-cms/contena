<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\LandingPage\LandingPageCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CmsPageEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $name = null;

    protected string $type;

    protected ?string $entity = null;

    protected ?CmsSectionCollection $sections = null;

    /**
     * @var EntityCollection<CmsPageTranslationEntity>|null
     */
    protected ?EntityCollection $translations = null;

    protected ?CategoryCollection $categories = null;

    protected ?BlogCollection $blogs = null;

    protected ?string $cssClass = null;

    /**
     * @var array<string, array<string, mixed>>|null
     */
    protected ?array $config = null;

    protected ?string $previewMediaId = null;

    protected ?MediaEntity $previewMedia = null;

    protected bool $locked;

    protected ?LandingPageCollection $landingPages = null;

    protected ?CmsPageCollection $homeChannels = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(?string $entity): void
    {
        $this->entity = $entity;
    }

    public function getSections(): ?CmsSectionCollection
    {
        return $this->sections;
    }

    public function setSections(CmsSectionCollection $sections): void
    {
        $this->sections = $sections;
    }

    /**
     * @return EntityCollection<CmsPageTranslationEntity>|null
     */
    public function getTranslations(): ?EntityCollection
    {
        return $this->translations;
    }

    /**
     * @param EntityCollection<CmsPageTranslationEntity> $translations
     */
    public function setTranslations(EntityCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getCategories(): ?CategoryCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getBlogs(): ?BlogCollection
    {
        return $this->blogs;
    }

    public function setBlogs(BlogCollection $blogs): void
    {
        $this->blogs = $blogs;
    }

    public function getCssClass(): ?string
    {
        return $this->cssClass;
    }

    public function setCssClass(?string $cssClass): void
    {
        $this->cssClass = $cssClass;
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    /**
     * @param array<string, array<string, mixed>> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getPreviewMediaId(): ?string
    {
        return $this->previewMediaId;
    }

    public function setPreviewMediaId(string $previewMediaId): void
    {
        $this->previewMediaId = $previewMediaId;
    }

    public function getPreviewMedia(): ?MediaEntity
    {
        return $this->previewMedia;
    }

    public function setPreviewMedia(MediaEntity $previewMedia): void
    {
        $this->previewMedia = $previewMedia;
    }

    public function getLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): void
    {
        $this->locked = $locked;
    }

    public function getFirstElementOfType(string $type): ?CmsSlotEntity
    {
        $elements = $this->getElementsOfType($type);

        return array_shift($elements);
    }

    public function getLandingPages(): ?LandingPageCollection
    {
        return $this->landingPages;
    }

    public function setLandingPages(LandingPageCollection $landingPages): void
    {
        $this->landingPages = $landingPages;
    }

    public function getHomeChannels(): ?CmsPageCollection
    {
        return $this->homeChannels;
    }

    public function setHomeChannels(CmsPageCollection $homeChannels): void
    {
        $this->homeChannels = $homeChannels;
    }

    /**
     * @return list<CmsSlotEntity>
     */
    public function getElementsOfType(string $type): array
    {
        $elements = [];
        if ($this->getSections() === null) {
            return $elements;
        }

        foreach ($this->getSections()->getBlocks() as $block) {
            if ($block->getSlots() === null) {
                continue;
            }

            foreach ($block->getSlots() as $slot) {
                if ($slot->getType() === $type) {
                    $elements[] = $slot;
                }
            }
        }

        return $elements;
    }

    /**
     * @return list<CmsSlotEntity>
     */
    public function getAllElements(): array
    {
        if ($this->getSections() === null) {
            return [];
        }

        $elements = [];
        foreach ($this->getSections()->getBlocks() as $block) {
            if ($block->getSlots() === null) {
                continue;
            }

            foreach ($block->getSlots() as $slot) {
                $elements[] = $slot;
            }
        }

        return $elements;
    }
}
