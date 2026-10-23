<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelAnalytics;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelDefinition;

/**
 * @codeCoverageIgnore
 */
class ChannelAnalyticsDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'channel_analytics';

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
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of sales channel analytics.'),
            (new StringField('tracking_id', 'trackingId'))->setDescription('Unique identity for tracking.'),
            (new BoolField('active', 'active'))->setDescription('When boolean value is `true`, the sales channel analytics are enabled.'),
            (new BoolField('track_orders', 'trackOrders'))->setDescription('When boolean value is `true`, it enables Google Analytics to track orders.'),
            (new BoolField('anonymize_ip', 'anonymizeIp'))->setDescription('Unique identity of anonymize.'),
            (new BoolField('track_offcanvas_cart', 'trackOffcanvasCart'))->setDescription('When boolean value is `true`, it enables Google Analytics to track offcanvas cart.'),
            (new BoolField('enhanced_conversions', 'enhancedConversions'))->setDescription('When boolean value is `true`, it enables Google Ads Enhanced Conversions by sending SHA256-hashed user email with the purchase event.'),
            new OneToOneAssociationField('channel', 'id', 'analytics_id', ChannelDefinition::class, false),
        ]);
    }
}
