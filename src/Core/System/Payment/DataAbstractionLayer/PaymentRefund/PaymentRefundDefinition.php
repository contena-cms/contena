<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund;

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
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentChannelNotifyRecordDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderDefinition;

class PaymentRefundDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'payment_refund';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PaymentRefundCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaymentRefundEntity::class;
    }

    public function getDefaults(): array
    {
        return ['status' => 0, 'version' => 0];
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
            new StringField('refund_no', 'refundNo', 64)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new FkField('order_id', 'orderId', PaymentOrderDefinition::class)->addFlags(new ApiAware(), new Required()),
            new StringField('external_refund_no', 'externalRefundNo', 64)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new IntField('refund_amount', 'refundAmount')->addFlags(new ApiAware(), new Required()),
            new StringField('channel_code', 'channelCode', 32)->addFlags(new ApiAware(), new Required()),
            new IntField('status', 'status')->addFlags(new ApiAware(), new Required()),
            new StringField('channel_refund_no', 'channelRefundNo', 128)->addFlags(new ApiAware()),
            new DateTimeField('success_time', 'successTime')->addFlags(new ApiAware()),
            new StringField('reason', 'reason', 255)->addFlags(new ApiAware()),
            new JsonField('response_data', 'responseData')->addFlags(new ApiAware()),
            new StringField('result_code', 'resultCode', 64)->addFlags(new ApiAware()),
            new StringField('result_message', 'resultMessage', 255)->addFlags(new ApiAware()),
            new IntField('version', 'version')->addFlags(new ApiAware()),
            new CustomFields()->addFlags(new ApiAware()),
            new ManyToOneAssociationField('order', 'order_id', PaymentOrderDefinition::class)->addFlags(new ApiAware()),
            new OneToManyAssociationField('notifyRecords', PaymentNotifyRecordDefinition::class, 'refund_id')->addFlags(new ApiAware()),
            new OneToManyAssociationField('channelNotifyRecords', PaymentChannelNotifyRecordDefinition::class, 'refund_id')->addFlags(new ApiAware()),
        ]);
    }
}
