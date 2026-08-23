<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Aggregate\MediaThumbnailSize;

use Contena\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationCollection;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MediaThumbnailSizeEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    /**
     * @var int<1, max>
     */
    protected int $width;

    /**
     * @var int<1, max>
     */
    protected int $height;

    protected ?MediaFolderConfigurationCollection $mediaFolderConfigurations = null;

    protected ?MediaThumbnailCollection $mediaThumbnails = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    /**
     * @return int<1, max>
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * @param int<1, max> $width
     */
    public function setWidth(int $width): void
    {
        $this->width = $width;
    }

    /**
     * @return int<1, max>
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @param int<1, max> $height
     */
    public function setHeight(int $height): void
    {
        $this->height = $height;
    }

    public function getMediaFolderConfigurations(): ?MediaFolderConfigurationCollection
    {
        return $this->mediaFolderConfigurations;
    }

    public function setMediaFolderConfigurations(MediaFolderConfigurationCollection $mediaFolderConfigurations): void
    {
        $this->mediaFolderConfigurations = $mediaFolderConfigurations;
    }

    public function getMediaThumbnails(): ?MediaThumbnailCollection
    {
        return $this->mediaThumbnails;
    }

    public function setMediaThumbnails(MediaThumbnailCollection $mediaThumbnails): void
    {
        $this->mediaThumbnails = $mediaThumbnails;
    }
}
