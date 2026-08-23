<?php

declare(strict_types=1);

namespace Contena\Tests\Core\Framework\Foo;

use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class TestEntityDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([]);
    }
}
