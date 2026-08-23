<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog;

use Contena\Core\Content\Blog\Aggregate\BlogMainCategory\BlogMainCategoryCollection;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaCollection;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword\BlogSearchKeywordCollection;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Tag\TagCollection;

class BlogEntity extends Entity implements \Stringable
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected int $autoIncrement;

    protected bool $active;

    protected string $type = BlogDefinition::TYPE_POST;

    protected ?\DateTimeInterface $releaseDate = null;

    /**
     * @var array<string>|null
     */
    protected ?array $categoryTree = null;

    /**
     * @var array<string>|null
     */
    protected ?array $tagIds = null;

    /**
     * @var array<string>|null
     */
    protected ?array $categoryIds = null;

    protected ?string $name = null;

    protected ?string $keywords = null;

    /**
     * @var array<string>|null
     */
    protected ?array $customSearchKeywords = null;

    protected ?string $description = null;

    protected ?string $descriptionTeaser = null;

    protected ?string $metaDescription = null;

    protected ?string $metaTitle = null;

    protected ?string $ogTitle = null;

    protected ?string $ogDescription = null;

    protected ?string $coverId = null;

    protected ?BlogMediaEntity $cover = null;

    protected ?BlogMediaCollection $media = null;

    protected ?BlogSearchKeywordCollection $searchKeywords = null;

    protected ?string $openGraphMediaId = null;

    protected ?MediaEntity $openGraphMedia = null;

    protected ?BlogTranslationCollection $translations = null;

    protected ?CategoryCollection $categories = null;

    protected ?CategoryCollection $categoriesRo = null;

    protected ?TagCollection $tags = null;

    protected ?BlogVisibilityCollection $visibilities = null;

    protected ?BlogMainCategoryCollection $mainCategories = null;

    protected ?SeoUrlCollection $seoUrls = null;

    public function __toString(): string
    {
        return (string) ($this->getTranslation('name') ?? $this->name);
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getAutoIncrement(): int
    {
        return $this->autoIncrement;
    }

    public function setAutoIncrement(int $autoIncrement): void
    {
        $this->autoIncrement = $autoIncrement;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getReleaseDate(): ?\DateTimeInterface
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?\DateTimeInterface $releaseDate): void
    {
        $this->releaseDate = $releaseDate;
    }

    /**
     * @return array<string>|null
     */
    public function getCategoryTree(): ?array
    {
        return $this->categoryTree;
    }

    /**
     * @param array<string>|null $categoryTree
     */
    public function setCategoryTree(?array $categoryTree): void
    {
        $this->categoryTree = $categoryTree;
    }

    /**
     * @return array<string>|null
     */
    public function getTagIds(): ?array
    {
        return $this->tagIds;
    }

    /**
     * @param array<string>|null $tagIds
     */
    public function setTagIds(?array $tagIds): void
    {
        $this->tagIds = $tagIds;
    }

    /**
     * @return array<string>|null
     */
    public function getCategoryIds(): ?array
    {
        return $this->categoryIds;
    }

    /**
     * @param array<string>|null $categoryIds
     */
    public function setCategoryIds(?array $categoryIds): void
    {
        $this->categoryIds = $categoryIds;
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

    public function getCoverId(): ?string
    {
        return $this->coverId;
    }

    public function setCoverId(?string $coverId): void
    {
        $this->coverId = $coverId;
    }

    public function getCover(): ?BlogMediaEntity
    {
        return $this->cover;
    }

    public function setCover(?BlogMediaEntity $cover): void
    {
        $this->cover = $cover;
    }

    public function getMedia(): ?BlogMediaCollection
    {
        return $this->media;
    }

    public function setMedia(BlogMediaCollection $media): void
    {
        $this->media = $media;
    }

    public function getSearchKeywords(): ?BlogSearchKeywordCollection
    {
        return $this->searchKeywords;
    }

    public function setSearchKeywords(BlogSearchKeywordCollection $searchKeywords): void
    {
        $this->searchKeywords = $searchKeywords;
    }

    public function getOpenGraphMediaId(): ?string
    {
        return $this->openGraphMediaId;
    }

    public function setOpenGraphMediaId(?string $openGraphMediaId): void
    {
        $this->openGraphMediaId = $openGraphMediaId;
    }

    public function getOpenGraphMedia(): ?MediaEntity
    {
        return $this->openGraphMedia;
    }

    public function setOpenGraphMedia(?MediaEntity $openGraphMedia): void
    {
        $this->openGraphMedia = $openGraphMedia;
    }

    public function getTranslations(): ?BlogTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(BlogTranslationCollection $translations): void
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

    public function getCategoriesRo(): ?CategoryCollection
    {
        return $this->categoriesRo;
    }

    public function setCategoriesRo(CategoryCollection $categoriesRo): void
    {
        $this->categoriesRo = $categoriesRo;
    }

    public function getTags(): ?TagCollection
    {
        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
    }

    public function getVisibilities(): ?BlogVisibilityCollection
    {
        return $this->visibilities;
    }

    public function setVisibilities(BlogVisibilityCollection $visibilities): void
    {
        $this->visibilities = $visibilities;
    }

    public function getMainCategories(): ?BlogMainCategoryCollection
    {
        return $this->mainCategories;
    }

    public function setMainCategories(BlogMainCategoryCollection $mainCategories): void
    {
        $this->mainCategories = $mainCategories;
    }

    public function getSeoUrls(): ?SeoUrlCollection
    {
        return $this->seoUrls;
    }

    public function setSeoUrls(SeoUrlCollection $seoUrls): void
    {
        $this->seoUrls = $seoUrls;
    }
}
