<?php declare(strict_types=1);

namespace Contena\Core\System\Currency\Aggregate\CurrencyTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Currency\CurrencyDefinition;

class CurrencyTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = 'currency_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CurrencyTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return CurrencyTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return CurrencyDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('short_name', 'shortName')->addFlags(new ApiAware()),
            new StringField('name', 'name')->addFlags(new ApiAware(), new Required()),
            new CustomFields()->addFlags(new ApiAware()),
        ]);
    }
}
