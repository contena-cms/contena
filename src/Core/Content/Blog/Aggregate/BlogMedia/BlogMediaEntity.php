<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogMedia;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class BlogMediaEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $blogId;

    protected string $mediaId;

    protected int $position;

    protected ?MediaEntity $media = null;

    protected ?BlogEntity $blog = null;

    protected ?BlogCollection $coverBlogs = null;

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

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function setMediaId(string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getBlog(): ?BlogEntity
    {
        return $this->blog;
    }

    public function setBlog(BlogEntity $blog): void
    {
        $this->blog = $blog;
    }

    public function getCoverBlogs(): ?BlogCollection
    {
        return $this->coverBlogs;
    }

    public function setCoverBlogs(BlogCollection $coverBlogs): void
    {
        $this->coverBlogs = $coverBlogs;
    }
}
