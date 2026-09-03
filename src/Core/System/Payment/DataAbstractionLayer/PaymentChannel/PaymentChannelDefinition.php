<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelTranslation\PaymentChannelTranslationDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\PaymentChannelMethodDefinition;

class PaymentChannelDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'payment_channel';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PaymentChannelCollection::class;
    }

    public function getEntityClass(): string
    {
        return PaymentChannelEntity::class;
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
            new StringField('code', 'code', 32)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('name')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new JsonField('config_schema', 'configSchema')->addFlags(new ApiAware()),
            new BoolField('status', 'status')->addFlags(new ApiAware(), new Required()),
            new IntField('sort', 'sort')->addFlags(new ApiAware(), new Required()),
            new OneToManyAssociationField('methods', PaymentChannelMethodDefinition::class, 'channel_id')->addFlags(new ApiAware(), new RestrictDelete()),
            new OneToManyAssociationField('configs', PaymentChannelConfigDefinition::class, 'channel_id')->addFlags(new ApiAware(), new RestrictDelete()),
            new TranslationsAssociationField(PaymentChannelTranslationDefinition::class, 'payment_channel_id')->addFlags(new ApiAware(), new CascadeDelete(), new Required()),
        ]);
    }
}
