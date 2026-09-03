<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

class PaymentTransferDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'payment_transfer';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PaymentTransferCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaymentTransferEntity::class;
    }

    public function getDefaults(): array
    {
        return ['version' => 0];
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new FkField('payment_app_id', 'paymentAppId', PaymentAppDefinition::class)->addFlags(new ApiAware(), new Required()),
            new StringField('transfer_no', 'transferNo', 64)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new StringField('external_transfer_no', 'externalTransferNo', 64)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new IntField('amount', 'amount')->addFlags(new ApiAware(), new Required()),
            new StringField('currency_code', 'currencyCode', 3)->addFlags(new ApiAware(), new Required()),
            new StringField('channel_code', 'channelCode', 32)->addFlags(new ApiAware(), new Required()),
            new FkField('channel_config_id', 'channelConfigId', PaymentChannelConfigDefinition::class)->addFlags(new ApiAware(), new Required()),
            new StateMachineStateField('state_id', 'stateId', PaymentTransferStates::STATE_MACHINE)->addFlags(new ApiAware(), new Required()),
            new StringField('payee', 'payee', 128)->addFlags(new ApiAware(), new Required()),
            new StringField('payee_name', 'payeeName', 64)->addFlags(new ApiAware(), new Required()),
            new StringField('remark', 'remark', 255)->addFlags(new ApiAware()),
            new StringField('channel_order_id', 'channelOrderId', 128)->addFlags(new ApiAware()),
            new StringField('channel_status', 'channelStatus', 32)->addFlags(new ApiAware()),
            new DateTimeField('success_time', 'successTime')->addFlags(new ApiAware()),
            new IntField('version', 'version')->addFlags(new ApiAware()),
            new CustomFields()->addFlags(new ApiAware()),
            new ManyToOneAssociationField('app', 'payment_app_id', PaymentAppDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('channelConfig', 'channel_config_id', PaymentChannelConfigDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('state', 'state_id', StateMachineStateDefinition::class)->addFlags(new ApiAware()),
        ]);
    }
}
