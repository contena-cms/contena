<?php declare(strict_types=1);

namespace Contena\Core\Content\Media;

use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Contena\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationCollection;
use Contena\Core\Content\Media\MediaType\MediaType;
use Contena\Core\Content\Media\MediaType\SpatialObjectType;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Tag\TagCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;

/**
 * @phpstan-type MediaConfig array{'spatialObject': array{'arReady': bool, 'arPlacement': string}}
 */
class MediaEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected ?string $userId = null;

    protected ?string $mimeType = null;

    protected ?string $fileExtension = null;

    protected ?int $fileSize = null;

    protected ?string $title = null;

    protected ?string $metaDataRaw = null;

    /**
     * @internal
     */
    protected ?string $mediaTypeRaw = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $metaData = null;

    protected ?MediaType $mediaType = null;

    protected ?\DateTimeInterface $uploadedAt = null;

    protected ?string $alt = null;

    protected string $url = '';

    protected ?string $fileName = null;

    protected ?UserEntity $user = null;

    protected ?MediaTranslationCollection $translations = null;

    protected ?UserCollection $avatarUsers = null;

    protected ?MediaThumbnailCollection $thumbnails = null;

    protected ?string $mediaFolderId = null;

    protected ?MediaFolderEntity $mediaFolder = null;

    protected bool $hasFile = false;

    protected bool $private = false;

    protected ?TagCollection $tags = null;

    /**
     * @internal
     */
    protected ?string $thumbnailsRo = null;

    protected ?string $path = null;

    /**
     * @var MediaConfig|null
     */
    protected ?array $config = null;

    /**
     * @internal
     */
    protected ?string $fileHash = null;

    public function get(string $property)
    {
        if ($property === 'hasFile') {
            return $this->hasFile();
        }

        return parent::get($property);
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getFileExtension(): ?string
    {
        return $this->fileExtension;
    }

    public function setFileExtension(string $fileExtension): void
    {
        $this->fileExtension = $fileExtension;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): void
    {
        $this->fileSize = $fileSize;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetaData(): ?array
    {
        return $this->metaData;
    }

    /**
     * @param array<string, mixed> $metaData
     */
    public function setMetaData(array $metaData): void
    {
        $this->metaData = $metaData;
    }

    public function getMediaType(): ?MediaType
    {
        return $this->mediaType;
    }

    public function setMediaType(MediaType $mediaType): void
    {
        $this->mediaType = $mediaType;
    }

    public function getUploadedAt(): ?\DateTimeInterface
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeInterface $uploadedAt): void
    {
        $this->uploadedAt = $uploadedAt;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(string $alt): void
    {
        $this->alt = $alt;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(UserEntity $user): void
    {
        $this->user = $user;
    }

    public function getTranslations(): ?MediaTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(MediaTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getAvatarUsers(): ?UserCollection
    {
        return $this->avatarUsers;
    }

    public function setAvatarUsers(UserCollection $avatarUsers): void
    {
        $this->avatarUsers = $avatarUsers;
    }

    public function getThumbnails(): ?MediaThumbnailCollection
    {
        return $this->thumbnails;
    }

    public function setThumbnails(MediaThumbnailCollection $thumbnailCollection): void
    {
        $this->thumbnails = $thumbnailCollection;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function hasFile(): bool
    {
        $hasFile = $this->mimeType !== null && $this->fileExtension !== null && $this->fileName !== null;

        return $this->hasFile = $hasFile || $this->path !== null;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function getFileNameIncludingExtension(): ?string
    {
        if ($this->fileName === null || $this->fileExtension === null) {
            return null;
        }

        return \sprintf('%s.%s', $this->fileName, $this->fileExtension);
    }

    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    public function getMediaFolderId(): ?string
    {
        return $this->mediaFolderId;
    }

    public function setMediaFolderId(string $mediaFolderId): void
    {
        $this->mediaFolderId = $mediaFolderId;
    }

    public function getMediaFolder(): ?MediaFolderEntity
    {
        return $this->mediaFolder;
    }

    public function setMediaFolder(MediaFolderEntity $mediaFolder): void
    {
        $this->mediaFolder = $mediaFolder;
    }

    public function getMetaDataRaw(): ?string
    {
        return $this->metaDataRaw;
    }

    public function setMetaDataRaw(string $metaDataRaw): void
    {
        $this->metaDataRaw = $metaDataRaw;
    }

    /**
     * @internal
     */
    public function getMediaTypeRaw(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('mediaTypeRaw');

        return $this->mediaTypeRaw;
    }

    /**
     * @internal
     */
    public function setMediaTypeRaw(string $mediaTypeRaw): void
    {
        $this->mediaTypeRaw = $mediaTypeRaw;
    }

    public function getTags(): ?TagCollection
    {
        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
    }

    /**
     * @internal
     */
    public function getThumbnailsRo(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('thumbnailsRo');

        return $this->thumbnailsRo;
    }

    /**
     * @internal
     */
    public function setThumbnailsRo(string $thumbnailsRo): void
    {
        $this->thumbnailsRo = $thumbnailsRo;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        unset($data['metaDataRaw'], $data['mediaTypeRaw']);
        $data['hasFile'] = $this->hasFile();

        return $data;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function setPrivate(bool $private): void
    {
        $this->private = $private;
    }

    public function hasPath(): bool
    {
        return $this->path !== null;
    }

    public function getPath(): string
    {
        return $this->path ?? '';
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    /**
     * @return MediaConfig|null
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    /**
     * @param MediaConfig|null $configuration
     */
    public function setConfig(?array $configuration): void
    {
        $this->config = $configuration;
    }

    public function isSpatialObject(): bool
    {
        return $this->mediaType instanceof SpatialObjectType;
    }

    /**
     * @internal
     */
    public function getFileHash(): ?string
    {
        return $this->fileHash;
    }
}
