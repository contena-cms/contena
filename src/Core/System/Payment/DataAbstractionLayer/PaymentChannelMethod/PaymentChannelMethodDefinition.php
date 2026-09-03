<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\PaymentChannelDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\Aggregate\PaymentChannelMethodTranslation\PaymentChannelMethodTranslationDefinition;

class PaymentChannelMethodDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'payment_channel_method';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PaymentChannelMethodCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaymentChannelMethodEntity::class;
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
            new FkField('channel_id', 'channelId', PaymentChannelDefinition::class)->addFlags(new ApiAware(), new Required()),
            new StringField('method_code', 'methodCode', 32)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('name')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new BoolField('status', 'status')->addFlags(new ApiAware(), new Required()),
            new IntField('sort', 'sort')->addFlags(new ApiAware(), new Required()),
            new ManyToOneAssociationField('channel', 'channel_id', PaymentChannelDefinition::class)->addFlags(new ApiAware()),
            new OneToManyAssociationField('appChannelMethods', PaymentAppChannelMethodDefinition::class, 'channel_method_id')->addFlags(new ApiAware(), new RestrictDelete()),
            new TranslationsAssociationField(PaymentChannelMethodTranslationDefinition::class, 'payment_channel_method_id')->addFlags(new ApiAware(), new CascadeDelete(), new Required()),
        ]);
    }
}
