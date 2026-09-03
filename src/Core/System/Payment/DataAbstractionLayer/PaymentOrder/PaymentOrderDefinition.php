<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowPlatformOwnedReference;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentChannelNotifyRecordDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

class PaymentOrderDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'payment_order';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PaymentOrderCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaymentOrderEntity::class;
    }

    public function getDefaults(): array
    {
        return ['currencyCode' => 'CNY', 'refundedAmount' => 0, 'version' => 0];
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
            new StringField('order_no', 'orderNo', 64)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new StringField('external_order_no', 'externalOrderNo', 64)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new IntField('amount', 'amount')->addFlags(new ApiAware(), new Required()),
            new IntField('refunded_amount', 'refundedAmount')->addFlags(new ApiAware()),
            new StringField('currency_code', 'currencyCode', 3)->addFlags(new ApiAware(), new Required()),
            new StringField('channel_code', 'channelCode', 32)->addFlags(new ApiAware(), new Required()),
            new StringField('method_code', 'methodCode', 32)->addFlags(new ApiAware(), new Required()),
            new StringField('device_type', 'deviceType', 32)->addFlags(new ApiAware(), new Required()),
            new StringField('subject', 'subject', 255)->addFlags(new ApiAware(), new Required()),
            new StringField('client_ip', 'clientIp', 64)->addFlags(new ApiAware()),
            new JsonField('channel_extra', 'channelExtra')->addFlags(new ApiAware()),
            new StringField('notify_url', 'notifyUrl', 2048)->addFlags(new ApiAware()),
            new StringField('return_url', 'returnUrl', 2048)->addFlags(new ApiAware()),
            new StateMachineStateField('state_id', 'stateId', PaymentOrderStates::STATE_MACHINE)->addFlags(new ApiAware(), new Required()),
            new StringField('close_reason', 'closeReason', 32)->addFlags(new ApiAware()),
            new StringField('channel_trade_no', 'channelTradeNo', 128)->addFlags(new ApiAware()),
            new FkField('channel_config_id', 'channelConfigId', PaymentChannelConfigDefinition::class)->addFlags(new ApiAware(), new Required(), new AllowPlatformOwnedReference()),
            new DateTimeField('success_time', 'successTime')->addFlags(new ApiAware()),
            new DateTimeField('expire_time', 'expireTime')->addFlags(new ApiAware()),
            new IntField('version', 'version')->addFlags(new ApiAware()),
            new FkField('primary_transaction_id', 'primaryTransactionId', PaymentOrderTransactionDefinition::class)->addFlags(new ApiAware()),
            new FkField('recurring_id', 'recurringId', PaymentRecurringDefinition::class)->addFlags(new ApiAware()),
            new CustomFields()->addFlags(new ApiAware()),
            new ManyToOneAssociationField('app', 'payment_app_id', PaymentAppDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('state', 'state_id', StateMachineStateDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('channelConfig', 'channel_config_id', PaymentChannelConfigDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('recurring', 'recurring_id', PaymentRecurringDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('primaryTransaction', 'primary_transaction_id', PaymentOrderTransactionDefinition::class)->addFlags(new ApiAware()),
            new OneToManyAssociationField('refunds', PaymentRefundDefinition::class, 'order_id')->addFlags(new ApiAware()),
            new OneToManyAssociationField('transactions', PaymentOrderTransactionDefinition::class, 'order_id')->addFlags(new ApiAware()),
            new OneToManyAssociationField('notifyRecords', PaymentNotifyRecordDefinition::class, 'order_id')->addFlags(new ApiAware()),
            new OneToManyAssociationField('channelNotifyRecords', PaymentChannelNotifyRecordDefinition::class, 'order_id')->addFlags(new ApiAware()),
        ]);
    }
}
