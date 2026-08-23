<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Aggregate;

use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Frontend\Theme\ThemeDefinition;

class ThemeMediaDefinition extends MappingEntityDefinition
{
    final public const string ENTITY_NAME = 'theme_media';

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
        return new FieldCollection([
            new FkField('theme_id', 'themeId', ThemeDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new FkField('media_id', 'mediaId', MediaDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('theme', 'theme_id', ThemeDefinition::class),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class),
        ]);
    }
}
