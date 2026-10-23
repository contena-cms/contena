<?php declare(strict_types=1);

namespace Contena\Core\System\Currency;

use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceDefinition;
use Contena\Core\Content\BlogExport\BlogExportDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CashRoundingConfigField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
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
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingDefinition;
use Contena\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelCurrency\ChannelCurrencyDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Core\System\Channel\ChannelDefinition;

/**
 * @codeCoverageIgnore
 */
class CurrencyDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'currency';

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

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of currency.'),
            (new FloatField('factor', 'factor'))->addFlags(new ApiAware(), new Required())->setDescription('Currency exchange rate.'),
            (new StringField('symbol', 'symbol'))->addFlags(new ApiAware(), new Required())->setDescription('A currency symbol is a graphical representation used as shorthand for a currency\'s name, for example US Dollar - $'),
            (new StringField('iso_code', 'isoCode', 3))->addFlags(new ApiAware(), new Required())->setDescription('Standard international three digit code to represent currency. For example, USD.'),
            (new TranslatedField('shortName'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new IntField('position', 'position'))->addFlags(new ApiAware())->setDescription('The order of the tabs for multiple currencies defined.'),
            (new BoolField('is_system_default', 'isSystemDefault'))->addFlags(new ApiAware(), new Runtime()),
            (new FloatField('tax_free_from', 'taxFreeFrom'))->addFlags(new ApiAware())->setDescription('The value from which the tax must be exempted.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new TranslationsAssociationField(CurrencyTranslationDefinition::class, 'currency_id'))->addFlags(new Required()),
            (new OneToManyAssociationField('channelDefaultAssignments', ChannelDefinition::class, 'currency_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'currency_id', 'id'))->addFlags(new RestrictDelete()),
            new ManyToManyAssociationField('channels', ChannelDefinition::class, ChannelCurrencyDefinition::class, 'currency_id', 'channel_id'),
            (new OneToManyAssociationField('channelDomains', ChannelDomainDefinition::class, 'currency_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('promotionDiscountPrices', PromotionDiscountPriceDefinition::class, 'currency_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('blogExports', BlogExportDefinition::class, 'currency_id', 'id'))->addFlags(new RestrictDelete()),
            (new CashRoundingConfigField('item_rounding', 'itemRounding'))->addFlags(new ApiAware(), new Required())->setDescription('The cash rounding applied on net prices.'),
            (new CashRoundingConfigField('total_rounding', 'totalRounding'))->addFlags(new ApiAware(), new Required())->setDescription('The cash rounding applied on cart’s total net prices.'),
            (new OneToManyAssociationField('countryRoundings', CurrencyCountryRoundingDefinition::class, 'currency_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
