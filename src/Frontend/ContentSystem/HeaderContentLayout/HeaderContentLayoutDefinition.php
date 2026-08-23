<?php declare(strict_types=1);

namespace Contena\Frontend\ContentSystem\HeaderContentLayout;

use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Core\System\Channel\ChannelDefinition;

/**
 * Header content layout assignment with domain-aware resolution.
 *
 * Resolution priority: Domain+Channel → Channel → Global (null).
 * If domain_id is set, channel_id MUST also be set.
 *
 * @internal
 *
 * @final
 */
class HeaderContentLayoutDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'header_content_layout';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return HeaderContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return HeaderContentLayoutCollection::class;
    }

    public function since(): string
    {
        return '6.7.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned header layout assignment.'),
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            new FkField('domain_id', 'domainId', ChannelDomainDefinition::class)->addFlags(new ApiAware()),
            new FkField('channel_id', 'channelId', ChannelDefinition::class)->addFlags(new ApiAware()),
            new FkField('content_layout_id', 'contentLayoutId', ContentLayoutDefinition::class)->addFlags(new ApiAware(), new Required()),

            new ManyToOneAssociationField('domain', 'domain_id', ChannelDomainDefinition::class, 'id', false),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('contentLayout', 'content_layout_id', ContentLayoutDefinition::class, 'id', false),
        ]);
    }
}
