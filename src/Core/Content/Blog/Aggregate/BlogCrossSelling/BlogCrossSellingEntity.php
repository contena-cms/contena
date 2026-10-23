<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogCrossSelling;

use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs\BlogCrossSellingAssignedBlogsCollection;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingTranslation\BlogCrossSellingTranslationCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\ContentStream\ContentStreamEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class BlogCrossSellingEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $name = null;

    protected int $position;

    protected string $sortBy;

    protected string $sortDirection;

    protected int $limit;

    protected bool $active;

    protected string $blogId;

    protected ?BlogEntity $blog = null;

    protected ?string $contentStreamId = null;

    protected ?ContentStreamEntity $contentStream = null;

    protected string $type;

    protected ?BlogCrossSellingAssignedBlogsCollection $assignedBlogs = null;

    protected ?BlogCrossSellingTranslationCollection $translations = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getSortBy(): string
    {
        return $this->sortBy;
    }

    public function setSortBy(string $sortBy): void
    {
        $this->sortBy = $sortBy;
    }

    public function getSortDirection(): string
    {
        return $this->sortDirection;
    }

    public function setSortDirection(string $sortDirection): void
    {
        $this->sortDirection = $sortDirection;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    public function setBlogId(string $blogId): void
    {
        $this->blogId = $blogId;
    }

    public function getBlog(): ?BlogEntity
    {
        return $this->blog;
    }

    public function setBlog(BlogEntity $blog): void
    {
        $this->blog = $blog;
    }

    public function getBlogStreamId(): ?string
    {
        return $this->contentStreamId;
    }

    public function setBlogStreamId(string $contentStreamId): void
    {
        $this->contentStreamId = $contentStreamId;
    }

    public function getBlogStream(): ?ContentStreamEntity
    {
        return $this->contentStream;
    }

    public function setBlogStream(ContentStreamEntity $contentStream): void
    {
        $this->contentStream = $contentStream;
    }

    public function getTranslations(): ?BlogCrossSellingTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(BlogCrossSellingTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getSorting(): FieldSorting
    {
        return new FieldSorting($this->sortBy, $this->sortDirection);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getAssignedBlogs(): ?BlogCrossSellingAssignedBlogsCollection
    {
        return $this->assignedBlogs;
    }

    public function setAssignedBlogs(BlogCrossSellingAssignedBlogsCollection $assignedBlogs): void
    {
        $this->assignedBlogs = $assignedBlogs;
    }
}
