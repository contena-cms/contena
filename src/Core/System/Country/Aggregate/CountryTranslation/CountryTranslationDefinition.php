<?php declare(strict_types=1);

namespace Contena\Core\System\Country\Aggregate\CountryTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Country\CountryDefinition;

class CountryTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = 'country_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CountryTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return CountryTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return CountryDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('name', 'name')->addFlags(new ApiAware(), new Required()),
            new CustomFields()->addFlags(new ApiAware()),
        ]);
    }
}
