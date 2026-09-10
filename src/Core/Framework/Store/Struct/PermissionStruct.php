<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

/**
 * @codeCoverageIgnore
 */
class PermissionStruct extends StoreStruct
{
    protected string $entity;

    protected string $operation;

    public static function fromArray(array $data): self
    {
        return new self()->assign($data);
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): void
    {
        $this->entity = $entity;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): void
    {
        $this->operation = $operation;
    }
}
