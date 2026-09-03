<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentOperation;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferDefinition;

class PaymentOperationDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'payment_operation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PaymentOperationCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaymentOperationEntity::class;
    }

    public function getDefaults(): array
    {
        return ['status' => PaymentOperationStatus::CREATED];
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
            new StringField('operation_no', 'operationNo', 64)->addFlags(new ApiAware(), new Required()),
            new StringField('operation', 'operation', 32)->addFlags(new ApiAware(), new Required()),
            new StringField('status', 'status', 16)->addFlags(new ApiAware(), new Required()),
            new StringField('channel_code', 'channelCode', 32)->addFlags(new ApiAware(), new Required()),
            new FkField('channel_config_id', 'channelConfigId', PaymentChannelConfigDefinition::class)->addFlags(new ApiAware(), new Required()),
            new FkField('order_id', 'orderId', PaymentOrderDefinition::class)->addFlags(new ApiAware()),
            new FkField('refund_id', 'refundId', PaymentRefundDefinition::class)->addFlags(new ApiAware()),
            new FkField('transfer_id', 'transferId', PaymentTransferDefinition::class)->addFlags(new ApiAware()),
            new FkField('recurring_id', 'recurringId', PaymentRecurringDefinition::class)->addFlags(new ApiAware()),
            new StringField('provider_request_id', 'providerRequestId', 128)->addFlags(new ApiAware()),
            new StringField('provider_resource_id', 'providerResourceId', 128)->addFlags(new ApiAware()),
            new JsonField('request_data', 'requestData'),
            new JsonField('response_data', 'responseData'),
            new StringField('result_code', 'resultCode', 64)->addFlags(new ApiAware()),
            new StringField('result_message', 'resultMessage', 255)->addFlags(new ApiAware()),
            new IntField('http_status', 'httpStatus')->addFlags(new ApiAware()),
            new IntField('duration_ms', 'durationMs')->addFlags(new ApiAware()),
            new StringField('error_class', 'errorClass', 255)->addFlags(new ApiAware()),
            new DateTimeField('started_at', 'startedAt')->addFlags(new ApiAware(), new Required()),
            new DateTimeField('completed_at', 'completedAt')->addFlags(new ApiAware()),
            new CustomFields()->addFlags(new ApiAware()),
            new ManyToOneAssociationField('channelConfig', 'channel_config_id', PaymentChannelConfigDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('order', 'order_id', PaymentOrderDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('refund', 'refund_id', PaymentRefundDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('transfer', 'transfer_id', PaymentTransferDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('recurring', 'recurring_id', PaymentRecurringDefinition::class)->addFlags(new ApiAware()),
        ]);
    }
}
