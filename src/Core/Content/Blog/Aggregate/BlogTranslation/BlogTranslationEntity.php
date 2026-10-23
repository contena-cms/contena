<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogTranslation;

use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class BlogTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $blogId;

    protected string $blogVersionId;

    protected ?string $metaDescription = null;

    protected ?string $name = null;

    protected ?string $keywords = null;

    protected ?string $description = null;

    protected ?string $descriptionTeaser = null;

    protected ?string $metaTitle = null;

    protected ?string $packUnit = null;

    protected ?string $packUnitPlural = null;

    protected ?BlogEntity $blog = null;

    /**
     * @var array<string, array<string, array<string, mixed>>|null>|null
     */
    protected ?array $slotConfig = null;

    protected ?string $ogTitle = null;

    protected ?string $ogDescription = null;

    /**
     * @var array<string>|null
     */
    protected ?array $customSearchKeywords = null;

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

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): void
    {
        $this->keywords = $keywords;
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

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getPackUnit(): ?string
    {
        return $this->packUnit;
    }

    public function setPackUnit(?string $packUnit): void
    {
        $this->packUnit = $packUnit;
    }

    public function getPackUnitPlural(): ?string
    {
        return $this->packUnitPlural;
    }

    public function setPackUnitPlural(?string $packUnitPlural): void
    {
        $this->packUnitPlural = $packUnitPlural;
    }

    public function getBlog(): ?BlogEntity
    {
        return $this->blog;
    }

    public function setBlog(BlogEntity $blog): void
    {
        $this->blog = $blog;
    }

    /**
     * @return array<string, array<string, array<string, mixed>>|null>|null
     */
    public function getSlotConfig(): ?array
    {
        return $this->slotConfig;
    }

    /**
     * @param array<string, array<string, array<string, mixed>>|null> $slotConfig
     */
    public function setSlotConfig(array $slotConfig): void
    {
        $this->slotConfig = $slotConfig;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    /**
     * @return array<string>|null
     */
    public function getCustomSearchKeywords(): ?array
    {
        return $this->customSearchKeywords;
    }

    /**
     * @param array<string>|null $customSearchKeywords
     */
    public function setCustomSearchKeywords(?array $customSearchKeywords): void
    {
        $this->customSearchKeywords = $customSearchKeywords;
    }

    public function getBlogVersionId(): string
    {
        return $this->blogVersionId;
    }

    public function setBlogVersionId(string $blogVersionId): void
    {
        $this->blogVersionId = $blogVersionId;
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
