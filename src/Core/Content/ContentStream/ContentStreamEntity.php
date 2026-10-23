<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\ContentStream\Aggregate\ContentStreamFilter\ContentStreamFilterCollection;
use Contena\Core\Content\ContentStream\Aggregate\ContentStreamTranslation\ContentStreamTranslationCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class ContentStreamEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $name = null;

    protected ?string $description = null;

    /**
     * @var array<array<string, string|array<array<string, mixed>>>>|null
     */
    protected ?array $apiFilter = null;

    protected ?ContentStreamFilterCollection $filters = null;

    protected bool $invalid;

    protected bool $internal = false;

    protected bool $displayAsGroup = true;

    protected ?ContentStreamTranslationCollection $translations = null;

    protected ?BlogExportCollection $blogExports = null;

    protected ?BlogCrossSellingCollection $blogCrossSellings = null;

    protected ?CategoryCollection $categories = null;

    protected ?BlogCollection $blogs = null;

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

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return array<array<string, string|array<array<string, mixed>>>>|null
     */
    public function getApiFilter(): ?array
    {
        return $this->apiFilter;
    }

    /**
     * @param array<array<string, string|array<array<string, mixed>>>> $apiFilter
     */
    public function setApiFilter(?array $apiFilter): void
    {
        $this->apiFilter = $apiFilter;
    }

    public function getFilters(): ?ContentStreamFilterCollection
    {
        return $this->filters;
    }

    public function setFilters(ContentStreamFilterCollection $filters): void
    {
        $this->filters = $filters;
    }

    public function isInvalid(): bool
    {
        return $this->invalid;
    }

    public function setInvalid(bool $invalid): void
    {
        $this->invalid = $invalid;
    }

    public function isInternal(): bool
    {
        return $this->internal;
    }

    public function setInternal(bool $internal): void
    {
        $this->internal = $internal;
    }

    public function isDisplayAsGroup(): bool
    {
        return $this->displayAsGroup;
    }

    public function setDisplayAsGroup(bool $displayAsGroup): void
    {
        $this->displayAsGroup = $displayAsGroup;
    }

    public function getTranslations(): ?ContentStreamTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ContentStreamTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getBlogExports(): ?BlogExportCollection
    {
        return $this->blogExports;
    }

    public function setBlogExports(BlogExportCollection $blogExports): void
    {
        $this->blogExports = $blogExports;
    }

    public function getBlogCrossSellings(): ?BlogCrossSellingCollection
    {
        return $this->blogCrossSellings;
    }

    public function setBlogCrossSellings(BlogCrossSellingCollection $blogCrossSellings): void
    {
        $this->blogCrossSellings = $blogCrossSellings;
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
}
