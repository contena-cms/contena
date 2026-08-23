<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Aggregate\MemberTag;

use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Tag\TagDefinition;

class MemberTagDefinition extends MappingEntityDefinition
{
    final public const string ENTITY_NAME = 'member_tag';

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
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned member tag assignment.'),
            new FkField('member_id', 'memberId', MemberDefinition::class)->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new FkField('tag_id', 'tagId', TagDefinition::class)->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('member', 'member_id', MemberDefinition::class, 'id', false),
            new ManyToOneAssociationField('tag', 'tag_id', TagDefinition::class, 'id', false)->addFlags(new ApiAware()),
        ]);
    }
}
