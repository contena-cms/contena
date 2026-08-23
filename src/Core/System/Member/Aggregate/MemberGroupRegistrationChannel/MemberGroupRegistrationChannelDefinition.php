<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Aggregate\MemberGroupRegistrationChannel;

use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupDefinition;

class MemberGroupRegistrationChannelDefinition extends MappingEntityDefinition
{
    final public const string ENTITY_NAME = 'member_group_registration_channel';

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
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned member group registration.'),
            new FkField('member_group_id', 'memberGroupId', MemberGroupDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new FkField('channel_id', 'channelId', ChannelDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('memberGroup', 'member_group_id', MemberGroupDefinition::class, 'id', false),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
        ]);
    }
}
