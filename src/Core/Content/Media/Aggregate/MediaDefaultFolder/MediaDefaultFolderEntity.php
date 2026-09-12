<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Aggregate\MediaDefaultFolder;

use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MediaDefaultFolderEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $dataScopeId;

    protected string $entity;

    protected ?MediaFolderEntity $folder = null;

    public function getDataScopeId(): string
    {
        return $this->dataScopeId;
    }

    public function setDataScopeId(string $dataScopeId): void
    {
        $this->dataScopeId = $dataScopeId;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): void
    {
        $this->entity = $entity;
    }

    public function getFolder(): ?MediaFolderEntity
    {
        return $this->folder;
    }

    public function setFolder(?MediaFolderEntity $folder): void
    {
        $this->folder = $folder;
    }
}
