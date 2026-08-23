<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelFile;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;

class ChannelFileDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'channel_file';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ChannelFileCollection::class;
    }

    public function getEntityClass(): string
    {
        return ChannelFileEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.12.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return ChannelDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required(), new ApiAware(AdminApiSource::class))->setDescription('Unique identity of the channel file configuration.'),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned channel file configuration.'),
            new FkField('channel_id', 'channelId', ChannelDefinition::class)->addFlags(new Required(), new ApiAware(AdminApiSource::class))->setDescription('Unique identity of the configured channel.'),
            new StringField('file_family', 'fileFamily', 64)->addFlags(new Required(), new ApiAware(AdminApiSource::class))->setDescription('File family below Resources/views/files.'),
            new StringField('file_name', 'fileName', 512)->addFlags(new Required(), new ApiAware(AdminApiSource::class))->setDescription('Normalized public file path without a leading slash.'),
            new BoolField('enabled', 'enabled')->addFlags(new Required(), new ApiAware(AdminApiSource::class))->setDescription('Controls whether the file is served for this channel.'),
            new JsonField('template_overrides', 'templateOverrides', [], [])->addFlags(new ApiAware(AdminApiSource::class))->setDescription('Twig template overrides keyed by Twig namespace.'),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
        ]);
    }
}
