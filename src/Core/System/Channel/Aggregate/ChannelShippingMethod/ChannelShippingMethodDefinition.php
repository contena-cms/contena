<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelShippingMethod;

use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\System\Channel\ChannelDefinition;

/**
 * @codeCoverageIgnore
 */
class ChannelShippingMethodDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'channel_shipping_method';

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
            (new FkField('channel_id', 'channelId', ChannelDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
        ]);
    }
}
