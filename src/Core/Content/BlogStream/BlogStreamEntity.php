<?php declare(strict_types=1);

namespace Contena\Core\Content\BlogStream;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\BlogStream\Aggregate\BlogStreamFilter\BlogStreamFilterCollection;
use Contena\Core\Content\BlogStream\Aggregate\BlogStreamTranslation\BlogStreamTranslationCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @codeCoverageIgnore
 */
class BlogStreamEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $name = null;

    protected ?string $description = null;

    /**
     * @var array<array<string, string|array<array<string, mixed>>>>|null
     */
    protected ?array $apiFilter = null;

    protected ?BlogStreamFilterCollection $filters = null;

    protected bool $invalid;

    protected bool $internal = false;

    protected bool $displayAsGroup = true;

    protected ?BlogStreamTranslationCollection $translations = null;



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

    public function getFilters(): ?BlogStreamFilterCollection
    {
        return $this->filters;
    }

    public function setFilters(BlogStreamFilterCollection $filters): void
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

    public function getTranslations(): ?BlogStreamTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(BlogStreamTranslationCollection $translations): void
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
}
