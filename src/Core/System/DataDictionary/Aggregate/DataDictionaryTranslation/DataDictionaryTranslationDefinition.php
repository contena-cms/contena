<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary\Aggregate\DataDictionaryTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\DataDictionary\DataDictionaryDefinition;

class DataDictionaryTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = 'data_dictionary_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DataDictionaryTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return DataDictionaryTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return DataDictionaryDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('label', 'label')->addFlags(new ApiAware(), new Required()),
            new LongTextField('description', 'description')->addFlags(new ApiAware()),
        ]);
    }
}
