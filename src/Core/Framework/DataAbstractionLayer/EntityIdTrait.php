<?php
declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer;

trait EntityIdTrait
{
    protected string $id;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
        $this->_uniqueIdentifier = $id;
    }
}
