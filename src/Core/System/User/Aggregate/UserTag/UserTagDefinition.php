<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserTag;

use Contena\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\System\Tag\TagDefinition;
use Contena\Core\System\User\UserDefinition;

class UserTagDefinition extends MappingEntityDefinition
{
    final public const string ENTITY_NAME = 'user_tag';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned user tag assignment.'),
            new FkField('user_id', 'userId', UserDefinition::class)->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new FkField('tag_id', 'tagId', TagDefinition::class)->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
            new ManyToOneAssociationField('tag', 'tag_id', TagDefinition::class, 'id', false)->addFlags(new ApiAware()),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
