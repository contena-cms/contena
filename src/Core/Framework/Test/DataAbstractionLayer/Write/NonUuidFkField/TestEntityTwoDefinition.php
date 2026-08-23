<?php

declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Write\NonUuidFkField;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal test class
 */
class TestEntityTwoDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_entity_two';
    }

    public function since(): ?string
    {
        return 'test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
            new NonUuidFkField('test_entity_one_technical_name', 'testEntityOneTechnicalName', TestEntityOneDefinition::class)->addFlags(new Required()),
            new ManyToOneAssociationField('testEntityOne', 'test_entity_one_technical_name', TestEntityOneDefinition::class, 'technical_name'),
        ]);
    }
}
