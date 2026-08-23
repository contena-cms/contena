<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\_fixtures;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\SimpleDefinition;

/**
 * @internal
 */
class SimpleExtendedDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'simple_extended';

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
                new IdField('id', 'id')->addFlags(new ApiAware(), new Required(), new PrimaryKey()),
                new FkField('simple_id', 'simpleId', SimpleDefinition::class),
                new JsonField('extended_json_field', 'extendedJsonField')->addFlags(new Extension()),

                new OneToOneAssociationField('simpleIdField', 'simple_id', 'id_field', SimpleDefinition::class, false),
            ]
        );
    }
}
