<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeDefinition;

class NumberRangeTypeTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = 'number_range_type_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return NumberRangeTypeTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return NumberRangeTypeTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return NumberRangeTypeDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('type_name', 'typeName')->addFlags(new Required()),
            new CustomFields(),
        ]);
    }
}
