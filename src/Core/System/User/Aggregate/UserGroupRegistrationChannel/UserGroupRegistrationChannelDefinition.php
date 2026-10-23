<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserGroupRegistrationChannel;

use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelDefinition;

/**
 * @codeCoverageIgnore
 */
class UserGroupRegistrationChannelDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'user_group_registration_channels';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.3.1.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('user_group_id', 'userGroupId', UserGroupDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('channel_id', 'channelId', ChannelDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('userGroup', 'user_group_id', UserGroupDefinition::class, 'id'),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id'),
            new CreatedAtField(),
        ]);
    }
}
