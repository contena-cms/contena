<?php declare(strict_types=1);

namespace Contena\Core\System\Tag;

use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class TagEntity extends Entity
{
    use EntityIdTrait;

    protected string $dataScopeId;

    protected string $name;

    protected ?MediaCollection $media = null;

    public function getDataScopeId(): string
    {
        return $this->dataScopeId;
    }

    public function setDataScopeId(string $dataScopeId): void
    {
        $this->dataScopeId = $dataScopeId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getMedia(): ?MediaCollection
    {
        return $this->media;
    }

    public function setMedia(MediaCollection $media): void
    {
        $this->media = $media;
    }
}
