<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\MainCategory;

use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Deprecation\BCChange\ReturnTypeWidening;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MainCategoryEntity extends Entity
{
    use EntityIdTrait;

    protected string $channelId;

    protected ?ChannelEntity $channel = null;

    protected string $categoryId;

    protected string $categoryVersionId;

    protected ?CategoryEntity $category = null;

    protected string $blogId;

    protected string $blogVersionId;

    protected ?BlogEntity $blog = null;

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function setChannelId(string $channelId): void
    {
        $this->channelId = $channelId;
    }

    public function getChannel(): ?ChannelEntity
    {
        return $this->channel;
    }

    public function setChannel(?ChannelEntity $channel): void
    {
        $this->channel = $channel;
    }

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function setCategoryId(string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    #[ReturnTypeWidening(version: 'v6.8.0', newType: '?' . CategoryEntity::class)]
    public function getCategory(): CategoryEntity
    {
        /** @deprecated tag:v6.8.0 - remove this fallback condition */
        if ($this->category === null) {
            return new CategoryEntity();
        }

        return $this->category;
    }

    public function setCategory(CategoryEntity $category): void
    {
        $this->category = $category;
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

    public function setBlog(?BlogEntity $blog): void
    {
        $this->blog = $blog;
    }

    public function getCategoryVersionId(): string
    {
        return $this->categoryVersionId;
    }

    public function setCategoryVersionId(string $categoryVersionId): void
    {
        $this->categoryVersionId = $categoryVersionId;
    }

    public function getBlogVersionId(): string
    {
        return $this->blogVersionId;
    }

    public function setBlogVersionId(string $blogVersionId): void
    {
        $this->blogVersionId = $blogVersionId;
    }
}
