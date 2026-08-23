<?php

declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Schema;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class DynamicEntityDefinition extends EntityDefinition
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
