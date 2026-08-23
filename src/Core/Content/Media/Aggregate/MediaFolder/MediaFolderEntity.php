<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Aggregate\MediaFolder;

use Contena\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderEntity;
use Contena\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MediaFolderEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $name;

    protected ?string $parentId = null;

    protected ?MediaFolderEntity $parent = null;

    protected int $childCount;

    protected ?MediaCollection $media = null;

    protected ?string $configurationId = null;

    protected ?MediaFolderConfigurationEntity $configuration = null;

    protected bool $useParentConfiguration;

    protected ?MediaFolderCollection $children = null;

    protected ?MediaDefaultFolderEntity $defaultFolder = null;

    protected ?string $defaultFolderId = null;

    protected ?string $path = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getParent(): ?MediaFolderEntity
    {
        return $this->parent;
    }

    public function setParent(?MediaFolderEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildCount(): int
    {
        return $this->childCount;
    }

    public function setChildCount(int $childCount): void
    {
        $this->childCount = $childCount;
    }

    public function getMedia(): ?MediaCollection
    {
        return $this->media;
    }

    public function setMedia(MediaCollection $media): void
    {
        $this->media = $media;
    }

    public function getConfigurationId(): ?string
    {
        return $this->configurationId;
    }

    public function setConfigurationId(?string $configurationId): void
    {
        $this->configurationId = $configurationId;
    }

    public function getConfiguration(): ?MediaFolderConfigurationEntity
    {
        return $this->configuration;
    }

    public function setConfiguration(?MediaFolderConfigurationEntity $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function getUseParentConfiguration(): bool
    {
        return $this->useParentConfiguration;
    }

    public function setUseParentConfiguration(bool $useParentConfiguration): void
    {
        $this->useParentConfiguration = $useParentConfiguration;
    }

    public function getChildren(): ?MediaFolderCollection
    {
        return $this->children;
    }

    public function setChildren(MediaFolderCollection $children): void
    {
        $this->children = $children;
    }

    public function getDefaultFolder(): ?MediaDefaultFolderEntity
    {
        return $this->defaultFolder;
    }

    public function setDefaultFolder(?MediaDefaultFolderEntity $defaultFolder): void
    {
        $this->defaultFolder = $defaultFolder;
    }

    public function getDefaultFolderId(): ?string
    {
        return $this->defaultFolderId;
    }

    public function setDefaultFolderId(?string $defaultFolderId): void
    {
        $this->defaultFolderId = $defaultFolderId;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }
}
