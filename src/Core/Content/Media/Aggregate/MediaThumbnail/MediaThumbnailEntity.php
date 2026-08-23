<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Aggregate\MediaThumbnail;

use Contena\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MediaThumbnailEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected ?string $path = null;

    protected int $width;

    protected int $height;

    protected ?string $url = null;

    protected string $mediaId;

    protected ?MediaEntity $media = null;

    protected string $mediaThumbnailSizeId;

    protected ?MediaThumbnailSizeEntity $mediaThumbnailSize = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): void
    {
        $this->height = $height;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function setMediaId(string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getMediaThumbnailSizeId(): string
    {
        return $this->mediaThumbnailSizeId;
    }

    public function setMediaThumbnailSizeId(string $mediaThumbnailSizeId): void
    {
        $this->mediaThumbnailSizeId = $mediaThumbnailSizeId;
    }

    public function getMediaThumbnailSize(): ?MediaThumbnailSizeEntity
    {
        return $this->mediaThumbnailSize;
    }

    public function setMediaThumbnailSize(MediaThumbnailSizeEntity $mediaThumbnailSize): void
    {
        $this->mediaThumbnailSize = $mediaThumbnailSize;
    }

    public function getIdentifier(): string
    {
        return \sprintf('%dx%d', $this->getWidth(), $this->getHeight());
    }

    public function getPath(): string
    {
        return $this->path ?? '';
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }
}
