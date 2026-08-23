<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Extension;

use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeTranslationDefinition;

class LanguageExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('themeTranslations', ThemeTranslationDefinition::class, 'language_id')->addFlags(new CascadeDelete())
        );
    }

    public function getEntityName(): string
    {
        return LanguageDefinition::ENTITY_NAME;
    }
}
