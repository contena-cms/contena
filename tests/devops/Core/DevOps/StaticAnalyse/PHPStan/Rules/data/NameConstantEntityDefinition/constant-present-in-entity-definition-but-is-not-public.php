<?php

declare(strict_types=1);

namespace Contena\Foo\Bar;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class Bar extends EntityDefinition
{
    private const string ENTITY_NAME = 'bar';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id'),
        ]);
    }
}
