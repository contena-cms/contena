<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring;

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
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigDefinition;

class PaymentRecurringDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'payment_recurring';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PaymentRecurringCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaymentRecurringEntity::class;
    }

    public function getDefaults(): array
    {
        return ['status' => PaymentRecurringStatus::STATUS_PENDING, 'version' => 0];
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
            new StringField('recurring_no', 'recurringNo', 64)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new StringField('external_recurring_no', 'externalRecurringNo', 32)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new StringField('channel_code', 'channelCode', 32)->addFlags(new ApiAware(), new Required()),
            new FkField('channel_config_id', 'channelConfigId', PaymentChannelConfigDefinition::class)->addFlags(new ApiAware(), new Required()),
            new StringField('channel_recurring_no', 'channelRecurringNo', 64)->addFlags(new ApiAware()),
            new JsonField('channel_params', 'channelParams')->addFlags(new ApiAware()),
            new JsonField('channel_extra', 'channelExtra')->addFlags(new ApiAware()),
            new StringField('notify_url', 'notifyUrl', 2048)->addFlags(new ApiAware()),
            new StringField('return_url', 'returnUrl', 2048)->addFlags(new ApiAware()),
            new JsonField('response_data', 'responseData')->addFlags(new ApiAware()),
            new StringField('result_code', 'resultCode', 64)->addFlags(new ApiAware()),
            new StringField('result_message', 'resultMessage', 255)->addFlags(new ApiAware()),
            new IntField('status', 'status')->addFlags(new ApiAware(), new Required()),
            new DateTimeField('sign_time', 'signTime')->addFlags(new ApiAware()),
            new DateTimeField('expire_time', 'expireTime')->addFlags(new ApiAware()),
            new StringField('period_type', 'periodType', 16)->addFlags(new ApiAware()),
            new IntField('period', 'period')->addFlags(new ApiAware()),
            new DateTimeField('execute_time', 'executeTime')->addFlags(new ApiAware()),
            new IntField('single_amount', 'singleAmount')->addFlags(new ApiAware()),
            new IntField('total_amount', 'totalAmount')->addFlags(new ApiAware()),
            new IntField('total_payments', 'totalPayments')->addFlags(new ApiAware()),
            new IntField('version', 'version')->addFlags(new ApiAware()),
            new CustomFields()->addFlags(new ApiAware()),
            new ManyToOneAssociationField('app', 'payment_app_id', PaymentAppDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('channelConfig', 'channel_config_id', PaymentChannelConfigDefinition::class)->addFlags(new ApiAware()),
        ]);
    }
}
