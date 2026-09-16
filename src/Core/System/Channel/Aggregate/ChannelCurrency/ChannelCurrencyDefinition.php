<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelCurrency;

use Contena\Core\Framework\DataAbstractionLayer\Field\DataScopeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Currency\CurrencyDefinition;

class ChannelCurrencyDefinition extends MappingEntityDefinition
{
    final public const string ENTITY_NAME = 'channel_currency';

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
            new DataScopeField()->setDescription('Non-null identity of the owning data scope for channel currency assignment.'),
            new FkField('channel_id', 'channelId', ChannelDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new FkField('currency_id', 'currencyId', CurrencyDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id', false),
        ]);
    }
}
