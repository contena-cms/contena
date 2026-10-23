<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogManufacturer;

use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturerTranslation\BlogManufacturerTranslationCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class BlogManufacturerEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $mediaId = null;

    protected ?string $name = null;

    protected ?string $link = null;

    protected ?string $description = null;

    protected ?MediaEntity $media = null;

    protected ?BlogManufacturerTranslationCollection $translations = null;

    protected ?BlogCollection $blogs = null;

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): void
    {
        $this->link = $link;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getTranslations(): ?BlogManufacturerTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(BlogManufacturerTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getBlogs(): ?BlogCollection
    {
        return $this->blogs;
    }

    public function setBlogs(BlogCollection $blogs): void
    {
        $this->blogs = $blogs;
    }
}
