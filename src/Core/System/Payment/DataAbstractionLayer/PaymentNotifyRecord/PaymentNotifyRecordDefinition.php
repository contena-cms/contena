<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundDefinition;

class PaymentNotifyRecordDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'payment_notify_record';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PaymentNotifyRecordCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaymentNotifyRecordEntity::class;
    }

    public function getDefaults(): array
    {
        return ['status' => 0, 'retryCount' => 0];
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
            new FkField('order_id', 'orderId', PaymentOrderDefinition::class)->addFlags(new ApiAware()),
            new FkField('refund_id', 'refundId', PaymentRefundDefinition::class)->addFlags(new ApiAware()),
            new IntField('notify_type', 'notifyType')->addFlags(new ApiAware(), new Required()),
            new StringField('notify_url', 'notifyUrl', 2048)->addFlags(new ApiAware(), new Required()),
            new LongTextField('request_body', 'requestBody'),
            new LongTextField('response_body', 'responseBody'),
            new IntField('status', 'status')->addFlags(new ApiAware(), new Required()),
            new IntField('retry_count', 'retryCount')->addFlags(new ApiAware(), new Required()),
            new DateTimeField('available_at', 'availableAt')->addFlags(new ApiAware()),
            new CustomFields()->addFlags(new ApiAware()),
            new ManyToOneAssociationField('order', 'order_id', PaymentOrderDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('refund', 'refund_id', PaymentRefundDefinition::class)->addFlags(new ApiAware()),
        ]);
    }
}
