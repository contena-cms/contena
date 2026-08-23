<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class AssociationExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('toMany', ExtendedDefinition::class, 'extendable_id')
                ->addFlags(new ApiAware())
        );

        $collection->add(
            new OneToOneAssociationField('toOne', 'id', 'extendable_id', ExtendedDefinition::class, false)
                ->addFlags(new ApiAware())
        );

        $collection->add(
            new OneToOneAssociationField('toOneWithoutApiAware', 'id', 'extendable_id', ExtendedDefinition::class, false)
                ->removeFlag(ApiAware::class)
        );
    }

    public function getEntityName(): string
    {
        return 'extendable';
    }
}
