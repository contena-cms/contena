<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogManufacturerTranslation;

use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class BlogManufacturerTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $blogManufacturerId;

    protected string $blogManufacturerVersionId;

    protected ?string $name = null;

    protected ?string $link = null;

    protected ?string $description = null;

    protected ?BlogManufacturerEntity $blogManufacturer = null;

    public function getBlogManufacturerId(): string
    {
        return $this->blogManufacturerId;
    }

    public function setBlogManufacturerId(string $blogManufacturerId): void
    {
        $this->blogManufacturerId = $blogManufacturerId;
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

    public function getBlogManufacturer(): ?BlogManufacturerEntity
    {
        return $this->blogManufacturer;
    }

    public function setBlogManufacturer(BlogManufacturerEntity $blogManufacturer): void
    {
        $this->blogManufacturer = $blogManufacturer;
    }

    public function getBlogManufacturerVersionId(): string
    {
        return $this->blogManufacturerVersionId;
    }

    public function setBlogManufacturerVersionId(string $blogManufacturerVersionId): void
    {
        $this->blogManufacturerVersionId = $blogManufacturerVersionId;
    }
}
