<?php

declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Write\NonUuidFkField;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal test class
 */
class TestEntityOneDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_entity_one';
    }

    public function since(): ?string
    {
        return 'test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('technical_name', 'technicalName')->addFlags(new PrimaryKey(), new Required()),
        ]);
    }
}
