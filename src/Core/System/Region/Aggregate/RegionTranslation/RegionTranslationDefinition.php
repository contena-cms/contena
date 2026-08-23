<?php declare(strict_types=1);

namespace Contena\Core\System\Region\Aggregate\RegionTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Region\RegionDefinition;

class RegionTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = 'region_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return RegionTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return RegionTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return RegionDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('name', 'name')->addFlags(new ApiAware(), new Required()),
            new StringField('short_name', 'shortName')->addFlags(new ApiAware()),
            new CustomFields()->addFlags(new ApiAware()),
        ]);
    }
}
