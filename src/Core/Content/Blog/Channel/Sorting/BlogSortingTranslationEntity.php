<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Sorting;

use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;

class BlogSortingTranslationEntity extends TranslationEntity
{
    use EntityIdTrait;

    protected string $blogSortingId;

    protected ?BlogSortingEntity $blogSorting = null;

    protected ?string $label = null;

    public function getBlogSortingId(): string
    {
        return $this->blogSortingId;
    }

    public function setBlogSortingId(string $blogSortingId): void
    {
        $this->blogSortingId = $blogSortingId;
    }

    public function getBlogSorting(): ?BlogSortingEntity
    {
        return $this->blogSorting;
    }

    public function setBlogSorting(?BlogSortingEntity $blogSorting): void
    {
        $this->blogSorting = $blogSorting;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getApiAlias(): string
    {
        return 'blog_sorting_translation';
    }
}
