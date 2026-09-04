<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

class PaymentOrderTransactionDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'payment_order_transaction';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PaymentOrderTransactionCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaymentOrderTransactionEntity::class;
    }

    public function getDefaults(): array
    {
        return ['amount' => 0];
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
            new FkField('order_id', 'orderId', PaymentOrderDefinition::class)->addFlags(new ApiAware(), new Required()),
            new StringField('transaction_no', 'transactionNo', 64)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new StringField('type', 'type', 32)->addFlags(new ApiAware(), new Required()),
            new StringField('channel_code', 'channelCode', 32)->addFlags(new ApiAware(), new Required()),
            new StringField('method_code', 'methodCode', 32)->addFlags(new ApiAware()),
            new IntField('amount', 'amount')->addFlags(new ApiAware(), new Required()),
            new StringField('channel_request_no', 'channelRequestNo', 128)->addFlags(new ApiAware()),
            new StringField('channel_trade_no', 'channelTradeNo', 128)->addFlags(new ApiAware()),
            new StateMachineStateField('state_id', 'stateId', PaymentTransactionStates::STATE_MACHINE)->addFlags(new ApiAware(), new Required()),
            new JsonField('response_data', 'responseData')->addFlags(new ApiAware()),
            new StringField('result_code', 'resultCode', 64)->addFlags(new ApiAware()),
            new StringField('result_message', 'resultMessage', 255)->addFlags(new ApiAware()),
            new StringField('operator', 'operator', 64)->addFlags(new ApiAware()),
            new CustomFields()->addFlags(new ApiAware()),
            new ManyToOneAssociationField('order', 'order_id', PaymentOrderDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('state', 'state_id', StateMachineStateDefinition::class)->addFlags(new ApiAware()),
        ]);
    }
}
