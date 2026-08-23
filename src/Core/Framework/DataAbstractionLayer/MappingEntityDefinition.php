<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer;

use Contena\Core\Framework\DataAbstractionLayer\Exception\MappingEntityClassesException;

abstract class MappingEntityDefinition extends EntityDefinition
{
    public function getCollectionClass(): string
    {
        throw new MappingEntityClassesException();
    }

    public function getEntityClass(): string
    {
        throw new MappingEntityClassesException();
    }

    protected function getBaseFields(): array
    {
        return [];
    }

    protected function defaultFields(): array
    {
        return [];
    }
}
