<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Foo;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class Bar
{
    public function foo(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'ccc';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection(
                    [new StringField('aaa', 'foo')]
                );
            }
        };
    }
}
