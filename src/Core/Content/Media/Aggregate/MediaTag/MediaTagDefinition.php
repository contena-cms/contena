<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Aggregate\MediaTag;

use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\System\Tag\TagDefinition;

class MediaTagDefinition extends MappingEntityDefinition
{
    final public const string ENTITY_NAME = 'media_tag';

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
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned media tag assignment.'),
            new FkField('media_id', 'mediaId', MediaDefinition::class)->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            new FkField('tag_id', 'tagId', TagDefinition::class)->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('tag', 'tag_id', TagDefinition::class, 'id', false)->addFlags(new ApiAware()),
        ]);
    }
}
