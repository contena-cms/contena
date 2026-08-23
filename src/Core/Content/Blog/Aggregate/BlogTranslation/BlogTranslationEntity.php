<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;

class BlogTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected ?string $tenantId = null;

    protected string $blogId;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?string $descriptionTeaser = null;

    protected ?string $keywords = null;

    /**
     * @var list<string>|null
     */
    protected ?array $customSearchKeywords = null;

    protected ?string $metaDescription = null;

    protected ?string $metaTitle = null;

    protected ?string $ogTitle = null;

    protected ?string $ogDescription = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    public function setBlogId(string $blogId): void
    {
        $this->blogId = $blogId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getDescriptionTeaser(): ?string
    {
        return $this->descriptionTeaser;
    }

    public function setDescriptionTeaser(?string $descriptionTeaser): void
    {
        $this->descriptionTeaser = $descriptionTeaser;
    }

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): void
    {
        $this->keywords = $keywords;
    }

    /**
     * @return list<string>|null
     */
    public function getCustomSearchKeywords(): ?array
    {
        return $this->customSearchKeywords;
    }

    /**
     * @param list<string>|null $customSearchKeywords
     */
    public function setCustomSearchKeywords(?array $customSearchKeywords): void
    {
        $this->customSearchKeywords = $customSearchKeywords;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getOgTitle(): ?string
    {
        return $this->ogTitle;
    }

    public function setOgTitle(?string $ogTitle): void
    {
        $this->ogTitle = $ogTitle;
    }

    public function getOgDescription(): ?string
    {
        return $this->ogDescription;
    }

    public function setOgDescription(?string $ogDescription): void
    {
        $this->ogDescription = $ogDescription;
    }
}
