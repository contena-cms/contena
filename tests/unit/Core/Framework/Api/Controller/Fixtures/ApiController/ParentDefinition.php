<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Controller\Fixtures\ApiController;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class ParentDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'parent_entity';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new FkField('first_child_one_to_one_id', 'firstChildOneToOneId', ChildDefinition::class),
            new OneToOneAssociationField('firstChildOneToOne', 'first_child_one_to_one_id', 'id', ChildDefinition::class, false),
            new FkField('second_child_one_to_one_id', 'secondChildOneToOneId', ChildDefinition::class),
            new OneToOneAssociationField('secondChildOneToOne', 'second_child_one_to_one_id', 'id', ChildDefinition::class, false),

            new FkField('first_child_many_to_one_id', 'firstChildManyToOneId', ChildDefinition::class),
            new ManyToOneAssociationField('firstChildManyToOne', 'first_child_many_to_one_id', ChildDefinition::class, 'id', false),
            new FkField('second_child_many_to_one_id', 'secondChildManyToOneId', ChildDefinition::class),
            new ManyToOneAssociationField('secondChildManyToOne', 'second_child_many_to_one_id', ChildDefinition::class, 'id', false),

            new OneToManyAssociationField('firstOneToManyChildren', ChildDefinition::class, 'first_parent_one_to_many_id'),
            new OneToManyAssociationField('secondOneToManyChildren', ChildDefinition::class, 'second_parent_one_to_many_id'),
        ]);
    }
}
