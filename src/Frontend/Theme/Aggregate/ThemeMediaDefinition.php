<?php declare(strict_types=1);

namespace Contena\Storefront\Theme\Aggregate;

use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class ThemeMediaDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'theme_media';

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
            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class),
        ]);
    }
}
