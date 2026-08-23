<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelAnalytics;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;

class ChannelAnalyticsDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'channel_analytics';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ChannelAnalyticsCollection::class;
    }

    public function getEntityClass(): string
    {
        return ChannelAnalyticsEntity::class;
    }

    public function since(): ?string
    {
        return '6.2.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of channel analytics.'),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for platform-owned channel analytics.'),
            new StringField('tracking_id', 'trackingId')->setDescription('Unique identity for tracking.'),
            new BoolField('active', 'active')->setDescription('When true, channel analytics are enabled.'),
            new BoolField('anonymize_ip', 'anonymizeIp')->setDescription('When true, IP addresses are anonymized.'),
            new OneToOneAssociationField('channel', 'id', 'analytics_id', ChannelDefinition::class, false),
        ]);
    }
}
