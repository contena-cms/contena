<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SimpleDefinition;

/**
 * @internal
 */
class ComplexDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'complex';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(
            [
                new IdField('id_field', 'idField')->addFlags(new ApiAware()),
                new ManyToOneAssociationField('simpleTo', 'simpleToId', SimpleDefinition::class)
                    ->addFlags(new ApiAware())
                    ->setDescription('A reference to a simple entity'),
                new OneToManyAssociationField('simpleManys', SimpleDefinition::class, 'ref_field')
                    ->addFlags(new ApiAware())
                    ->setDescription('Multiple simple entities'),
                new ManyToOneAssociationField('simpleToWithEmptyDescription', 'simpleToId', SimpleDefinition::class)
                    ->addFlags(new ApiAware())
                    ->setDescription(''),
            ]
        );
    }
}
