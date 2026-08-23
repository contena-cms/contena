<?php

declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class SubManyDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'root_sub_many';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.3.3.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new VersionField(),
            new StringField('name', 'name'),
            new FkField('root_sub_id', 'subId', SubDefinition::class, 'id')->addFlags(new ApiAware(), new Required()),
            new ReferenceVersionField(SubDefinition::class)->addFlags(new ApiAware(), new Required()),
            new ManyToOneAssociationField('sub', 'root_sub_id', SubDefinition::class, 'id', false),
        ]);
    }
}
