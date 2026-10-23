<?php declare(strict_types=1);

namespace Contena\Core\System\Country;

use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TaxFreeConfigField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\Aggregate\ChannelCountry\ChannelCountryDefinition;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Contena\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Contena\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingDefinition;
use Contena\Core\System\Tax\Aggregate\TaxRule\TaxRuleDefinition;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressDefinition;

/**
 * @codeCoverageIgnore
 */
class CountryDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'country';

    final public const TYPE_CUSTOMER_TAX_FREE = 'user-tax-free';

    final public const TYPE_COMPANY_TAX_FREE = 'company-tax-free';

    final public const DEFAULT_ADDRESS_FORMAT = [
        ['address/company', 'symbol/dash', 'address/department'],
        ['address/first_name', 'address/last_name'],
        ['address/street'],
        ['address/zipcode', 'address/city'],
        ['address/country'],
    ];

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CountryCollection::class;
    }

    public function getEntityClass(): string
    {
        return CountryEntity::class;
    }

    public function getDefaults(): array
    {
        $defaultTax = [
            'enabled' => false,
            'currencyId' => Defaults::CURRENCY,
            'amount' => 0,
        ];

        return [
            'vatIdRequired' => false,
            'postalCodeRequired' => false,
            'checkPostalCodePattern' => false,
            'checkAdvancedPostalCodePattern' => false,
            'userTax' => $defaultTax,
            'companyTax' => $defaultTax,
            'isEu' => false,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the country.'),

            (new IntField('position', 'position'))->addFlags(new ApiAware())->setDescription('Numerical value that indicates the order in which the defined countries must be displayed in the frontend.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the country is available for selection in the frontend.'),
            (new BoolField('shipping_available', 'shippingAvailable'))->addFlags(new ApiAware())->setDescription('The shipping availability for a country is enabled when boolean value is `true`.'),
            (new BoolField('display_state_in_registration', 'displayStateInRegistration'))->addFlags(new ApiAware())->setDescription('The country\'s state is displayed in the address when boolean value is `true`.'),
            (new BoolField('force_state_in_registration', 'forceStateInRegistration'))->addFlags(new ApiAware())->setDescription('State details in the address are force included when boolean value is `true`.'),
            (new BoolField('check_vat_id_pattern', 'checkVatIdPattern'))->addFlags(new ApiAware())->setDescription('Verify if VAT ID is valid or not.'),
            (new BoolField('vat_id_required', 'vatIdRequired'))->addFlags(new ApiAware())->setDescription('Set to true, if VAT ID is to be made mandatory.'),
            (new StringField('vat_id_pattern', 'vatIdPattern'))->addFlags(new ApiAware())->setDescription('Unique VAT ID with country code and numbers, for example - GB999 9999'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new TaxFreeConfigField('user_tax', 'userTax'))->addFlags(new ApiAware())->setDescription('Sum of money to be paid by the user.'),
            (new TaxFreeConfigField('company_tax', 'companyTax'))->addFlags(new ApiAware())->setDescription('Sum of money to be paid by the company.'),
            (new BoolField('postal_code_required', 'postalCodeRequired'))->addFlags(new ApiAware())->setDescription('The postal code is made mandatory specification in the address, when boolean value is `true`.'),
            (new BoolField('check_postal_code_pattern', 'checkPostalCodePattern'))->addFlags(new ApiAware())->setDescription('Verify for valid postal code pattern.'),
            (new BoolField('check_advanced_postal_code_pattern', 'checkAdvancedPostalCodePattern'))->addFlags(new ApiAware())->setDescription('Verify for advanced postal code pattern.'),
            (new StringField('advanced_postal_code_pattern', 'advancedPostalCodePattern', 1024))->addFlags(new ApiAware())->setDescription('Wildcard formatted zip codes to allow easy searching in the frontend based on initial constants, for example - 24****, 1856**.'),
            (new TranslatedField('addressFormat'))->addFlags(new ApiAware()),
            (new StringField('default_postal_code_pattern', 'defaultPostalCodePattern', 1024))->addFlags(new ApiAware())->setDescription('Default pattern of postal or zip code.'),
            (new BoolField('is_eu', 'isEu'))->addFlags(new ApiAware(), new Required()),

            (new OneToManyAssociationField('states', CountryStateDefinition::class, 'country_id', 'id'))
                ->addFlags(new ApiAware(), new CascadeDelete())->setDescription('States/provinces/regions within the country'),

            (new TranslationsAssociationField(CountryTranslationDefinition::class, 'country_id'))
                ->addFlags(new ApiAware(), new Required()),

            (new OneToManyAssociationField('userAddresses', UserAddressDefinition::class, 'country_id', 'id'))
                ->addFlags(new RestrictDelete()),

            (new OneToManyAssociationField('channelDefaultAssignments', ChannelDefinition::class, 'country_id', 'id'))
                ->addFlags(new RestrictDelete()),

            new ManyToManyAssociationField('channels', ChannelDefinition::class, ChannelCountryDefinition::class, 'country_id', 'channel_id'),

            (new OneToManyAssociationField('taxRules', TaxRuleDefinition::class, 'country_id', 'id'))
                ->addFlags(new RestrictDelete()),

            (new OneToManyAssociationField('currencyCountryRoundings', CurrencyCountryRoundingDefinition::class, 'country_id'))
                ->addFlags(new CascadeDelete()),
        ]);
    }
}
