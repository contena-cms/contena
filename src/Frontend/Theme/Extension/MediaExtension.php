<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Extension;

use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Frontend\Theme\Aggregate\ThemeMediaDefinition;
use Contena\Frontend\Theme\ThemeDefinition;

class MediaExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('themes', ThemeDefinition::class, 'preview_media_id')
        );

        $collection->add(
            new ManyToManyAssociationField('themeMedia', ThemeDefinition::class, ThemeMediaDefinition::class, 'media_id', 'theme_id')->addFlags(new RestrictDelete())
        );
    }

    public function getEntityName(): string
    {
        return MediaDefinition::ENTITY_NAME;
    }
}
