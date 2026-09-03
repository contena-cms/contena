<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\Aggregate\PaymentAppTranslation\PaymentAppTranslationDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderDefinition;

class PaymentAppDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'payment_app';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PaymentAppCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaymentAppEntity::class;
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
            new StringField('app_code', 'appCode', 64)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new StringField('app_secret', 'appSecret')->removeFlag(ApiAware::class)->addFlags(new Required()),
            new TranslatedField('name')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new BoolField('status', 'status')->addFlags(new ApiAware(), new Required()),
            new OneToManyAssociationField('channelConfigs', PaymentChannelConfigDefinition::class, 'payment_app_id')->addFlags(new ApiAware(), new RestrictDelete()),
            new OneToManyAssociationField('channelMethods', PaymentAppChannelMethodDefinition::class, 'payment_app_id')->addFlags(new ApiAware(), new RestrictDelete()),
            new OneToManyAssociationField('orders', PaymentOrderDefinition::class, 'payment_app_id')->addFlags(new ApiAware(), new RestrictDelete()),
            new TranslationsAssociationField(PaymentAppTranslationDefinition::class, 'payment_app_id')->addFlags(new ApiAware(), new CascadeDelete(), new Required()),
        ]);
    }
}
