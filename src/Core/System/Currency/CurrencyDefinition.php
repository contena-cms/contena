<?php declare(strict_types=1);

namespace Contena\Core\System\Currency;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\Aggregate\ChannelCurrency\ChannelCurrencyDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;

class CurrencyDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'currency';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CurrencyCollection::class;
    }

    public function getEntityClass(): string
    {
        return CurrencyEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'factor' => 1.0,
            'position' => 1,
            'decimalPrecision' => 2,
        ];
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the currency.'),
            new FloatField('factor', 'factor')->addFlags(new ApiAware(), new Required())->setDescription('Exchange factor relative to the system currency; payment operations never apply it automatically.'),
            new StringField('symbol', 'symbol', 16)->addFlags(new ApiAware(), new Required())->setDescription('Display symbol of the currency.'),
            new StringField('iso_code', 'isoCode', 3)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('ISO 4217 alphabetic currency code.'),
            new TranslatedField('shortName')->addFlags(new ApiAware()),
            new TranslatedField('name')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new IntField('position', 'position')->addFlags(new ApiAware(), new Required()),
            new IntField('decimal_precision', 'decimalPrecision')->addFlags(new ApiAware(), new Required())->setDescription('Number of minor-unit decimal places defined for the currency.'),
            new TranslatedField('customFields')->addFlags(new ApiAware()),
            new TranslationsAssociationField(CurrencyTranslationDefinition::class, 'currency_id')->addFlags(new ApiAware(), new CascadeDelete(), new Required()),
            new OneToManyAssociationField('channelDefaultAssignments', ChannelDefinition::class, 'currency_id', 'id')->addFlags(new RestrictDelete()),
            new ManyToManyAssociationField('channels', ChannelDefinition::class, ChannelCurrencyDefinition::class, 'currency_id', 'channel_id'),
            new OneToManyAssociationField('channelDomains', ChannelDomainDefinition::class, 'currency_id')->addFlags(new RestrictDelete()),
        ]);
    }
}
