<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\ApiDefinition\EntityDefinition;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\IgnoreInOpenapiSchema;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class SimpleDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'simple';

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
                new StringField('string_field', 'stringField')->addFlags(new ApiAware())->setDescription('A simple string field'),
                new IntField('int_field', 'intField')->addFlags(new ApiAware())->setDescription('A simple int field'),
                new FloatField('float_field', 'floatField')->addFlags(new ApiAware())->setDescription('A simple float field'),
                new BoolField('bool_field', 'boolField')->addFlags(new ApiAware())->setDescription('A simple bool field'),
                new IdField('id_field', 'idField')->addFlags(new ApiAware())->setDescription('A simple id field'),
                new StringField('i_am_a_new_field', 'i_am_a_new_field')->addFlags(new ApiAware(), new Since('6.3.9.9')),
                new ChildCountField()->addFlags(new ApiAware()),

                new StringField('ignore_field', 'ignoreApiAwareField')->addFlags(new ApiAware(), new IgnoreInOpenapiSchema()),
                new StringField('required_field', 'requiredField')->addFlags(new ApiAware(), new Required()),
                new StringField('read_only_field', 'readOnlyField')->addFlags(new ApiAware(), new WriteProtected()),
            ]
        );
    }
}
