<?php declare(strict_types=1);

namespace Contena\Core\Content\Property;

use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Contena\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class PropertyGroupEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $name = null;

    protected string $displayType;

    protected string $sortingType;

    protected ?string $description = null;

    protected ?int $position = null;

    protected bool $filterable;

    protected ?bool $visibleOnBlogDetailPage = null;

    protected ?PropertyGroupOptionCollection $options = null;

    protected ?PropertyGroupTranslationCollection $translations = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getFilterable(): bool
    {
        return $this->filterable;
    }

    public function setFilterable(bool $filterable): void
    {
        $this->filterable = $filterable;
    }

    public function getVisibleOnBlogDetailPage(): bool
    {
        return $this->visibleOnBlogDetailPage ?? false;
    }

    public function setVisibleOnBlogDetailPage(bool $visibleOnBlogDetailPage): void
    {
        $this->visibleOnBlogDetailPage = $visibleOnBlogDetailPage;
    }

    public function getOptions(): ?PropertyGroupOptionCollection
    {
        return $this->options;
    }

    public function setOptions(PropertyGroupOptionCollection $options): void
    {
        $this->options = $options;
    }

    public function getTranslations(): ?PropertyGroupTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(PropertyGroupTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function getDisplayType(): string
    {
        return $this->displayType;
    }

    public function setDisplayType(string $displayType): void
    {
        $this->displayType = $displayType;
    }

    public function getSortingType(): string
    {
        return $this->sortingType;
    }

    public function setSortingType(string $sortingType): void
    {
        $this->sortingType = $sortingType;
    }
}
