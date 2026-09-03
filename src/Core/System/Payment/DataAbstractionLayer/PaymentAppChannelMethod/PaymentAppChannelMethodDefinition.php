<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod;

use Contena\Core\Content\Rule\RuleDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\PaymentChannelMethodDefinition;

class PaymentAppChannelMethodDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'payment_app_channel_method';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PaymentAppChannelMethodCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaymentAppChannelMethodEntity::class;
    }

    public function getDefaults(): array
    {
        return ['status' => true, 'sort' => 0];
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
            new FkField('channel_method_id', 'channelMethodId', PaymentChannelMethodDefinition::class)->addFlags(new ApiAware(), new Required()),
            new JsonField('config', 'config')->addFlags(new ApiAware()),
            new IntField('sort', 'sort')->addFlags(new ApiAware(), new Required()),
            new BoolField('status', 'status')->addFlags(new ApiAware(), new Required()),
            new FkField('rule_id', 'ruleId', RuleDefinition::class)->addFlags(new ApiAware()),
            new CustomFields()->addFlags(new ApiAware()),
            new ManyToOneAssociationField('app', 'payment_app_id', PaymentAppDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('channelMethod', 'channel_method_id', PaymentChannelMethodDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class)->addFlags(new ApiAware()),
        ]);
    }
}
