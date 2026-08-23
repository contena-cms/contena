<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Aggregate\MediaFolderConfiguration;

use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Contena\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MediaFolderConfigurationEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected ?MediaFolderCollection $mediaFolders = null;

    protected bool $createThumbnails;

    protected bool $keepAspectRatio;

    protected int $thumbnailQuality;

    protected bool $private;

    protected ?bool $noAssociation = null;

    protected ?MediaThumbnailSizeCollection $mediaThumbnailSizes = null;

    /**
     * @internal
     */
    protected ?string $mediaThumbnailSizesRo = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getMediaFolders(): ?MediaFolderCollection
    {
        return $this->mediaFolders;
    }

    public function setMediaFolders(MediaFolderCollection $mediaFolders): void
    {
        $this->mediaFolders = $mediaFolders;
    }

    public function getCreateThumbnails(): bool
    {
        return $this->createThumbnails;
    }

    public function setCreateThumbnails(bool $createThumbnails): void
    {
        $this->createThumbnails = $createThumbnails;
    }

    public function getKeepAspectRatio(): bool
    {
        return $this->keepAspectRatio;
    }

    public function setKeepAspectRatio(bool $keepAspectRatio): void
    {
        $this->keepAspectRatio = $keepAspectRatio;
    }

    public function getMediaThumbnailSizes(): ?MediaThumbnailSizeCollection
    {
        return $this->mediaThumbnailSizes;
    }

    public function setMediaThumbnailSizes(MediaThumbnailSizeCollection $mediaThumbnailSizes): void
    {
        $this->mediaThumbnailSizes = $mediaThumbnailSizes;
    }

    public function getThumbnailQuality(): int
    {
        return $this->thumbnailQuality;
    }

    public function setThumbnailQuality(int $thumbnailQuality): void
    {
        $this->thumbnailQuality = $thumbnailQuality;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function setPrivate(bool $private): void
    {
        $this->private = $private;
    }

    /**
     * @internal
     */
    public function getMediaThumbnailSizesRo(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('mediaThumbnailSizesRo');

        return $this->mediaThumbnailSizesRo;
    }

    /**
     * @internal
     */
    public function setMediaThumbnailSizesRo(string $mediaThumbnailSizesRo): void
    {
        $this->mediaThumbnailSizesRo = $mediaThumbnailSizesRo;
    }

    public function isNoAssociation(): ?bool
    {
        return $this->noAssociation;
    }

    public function setNoAssociation(?bool $noAssociation): void
    {
        $this->noAssociation = $noAssociation;
    }
}
