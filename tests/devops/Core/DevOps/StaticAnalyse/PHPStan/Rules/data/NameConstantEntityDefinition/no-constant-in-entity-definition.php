<?php

declare(strict_types=1);

namespace Contena\Foo\Bar;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class Bar extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'bar';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id'),
        ]);
    }
}
