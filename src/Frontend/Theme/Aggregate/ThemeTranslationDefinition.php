<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Aggregate;

use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Frontend\Theme\ThemeDefinition;

class ThemeTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = 'theme_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ThemeTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return ThemeTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return ThemeDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('description', 'description')->addFlags(new ApiAware()),
            new CustomFields()->addFlags(new ApiAware()),
        ]);
    }
}
