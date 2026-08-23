<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;

class SystemConfigDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'system_config';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SystemConfigEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SystemConfigCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of system configuration.'),
            new StringField('configuration_key', 'configurationKey')->addFlags(new ApiAware(), new Required())->setDescription('Key of the system configuration value.'),
            new ConfigJsonField('configuration_value', 'configurationValue')->addFlags(new ApiAware(), new Required())->setDescription('System configuration value.'),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for platform configuration.'),
            new FkField('channel_id', 'channelId', ChannelDefinition::class)->addFlags(new ApiAware())->setDescription('Unique identity of channel.'),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false)->addFlags(new ApiAware()),
        ]);
    }
}
