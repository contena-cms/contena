<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig;

use Contena\Core\Content\Rule\RuleDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\PaymentChannelDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderDefinition;

class PaymentChannelConfigDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'payment_channel_config';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PaymentChannelConfigCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaymentChannelConfigEntity::class;
    }

    public function getDefaults(): array
    {
        return ['status' => true];
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
            new FkField('payment_app_id', 'paymentAppId', PaymentAppDefinition::class)->addFlags(new ApiAware()),
            new FkField('channel_id', 'channelId', PaymentChannelDefinition::class)->addFlags(new ApiAware(), new Required()),
            new JsonField('config', 'config'),
            new FkField('rule_id', 'ruleId', RuleDefinition::class)->addFlags(new ApiAware()),
            new BoolField('status', 'status')->addFlags(new ApiAware(), new Required()),
            new CustomFields()->addFlags(new ApiAware()),
            new ManyToOneAssociationField('app', 'payment_app_id', PaymentAppDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('channel', 'channel_id', PaymentChannelDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('rule', 'rule_id', RuleDefinition::class)->addFlags(new ApiAware()),
            new OneToManyAssociationField('orders', PaymentOrderDefinition::class, 'channel_config_id')->addFlags(new ApiAware(), new RestrictDelete()),
        ]);
    }
}
