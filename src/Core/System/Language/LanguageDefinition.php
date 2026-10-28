<?php declare(strict_types=1);

namespace Contena\Core\System\Language;

use Contena\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationDefinition;
use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Contena\Core\Content\ImportExport\ImportExportProfileTranslationDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation\MailTemplateTypeTranslationDefinition;
use Contena\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Contena\Core\Content\Product\Aggregate\ProductKeywordDictionary\ProductKeywordDictionaryDefinition;
use Contena\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Contena\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigDefinition;
use Contena\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordDefinition;
use Contena\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Contena\Core\Content\Product\SalesChannel\Sorting\ProductSortingTranslationDefinition;
use Contena\Core\Content\ProductStream\Aggregate\ProductStreamTranslation\ProductStreamTranslationDefinition;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationDefinition;
use Contena\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationDefinition;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\App\Aggregate\ActionButtonTranslation\ActionButtonTranslationDefinition;
use Contena\Core\Framework\App\Aggregate\AppScriptConditionTranslation\AppScriptConditionTranslationDefinition;
use Contena\Core\Framework\App\Aggregate\AppTranslation\AppTranslationDefinition;
use Contena\Core\Framework\App\Aggregate\FlowActionTranslation\AppFlowActionTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationDefinition;
use Contena\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationDefinition;
use Contena\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Contena\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Contena\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;
use Contena\Core\System\Locale\LocaleDefinition;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeTranslation\NumberRangeTranslationDefinition;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation\NumberRangeTypeTranslationDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationDefinition;
use Contena\Core\System\SalesChannel\SalesChannelDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationDefinition;
use Contena\Core\System\StateMachine\StateMachineTranslationDefinition;
use Contena\Core\System\Tax\Aggregate\TaxRuleTypeTranslation\TaxRuleTypeTranslationDefinition;
use Contena\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;

#[Package('fundamentals@discovery')]
class LanguageDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'language';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LanguageCollection::class;
    }

    public function getEntityClass(): string
    {
        return LanguageEntity::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        return ['active' => true];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of language.'),
            (new ParentFkField(self::class))->addFlags(new ApiAware()),
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of locale.'),
            (new FkField('translation_code_id', 'translationCodeId', LocaleDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of translation code.'),

            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required())->setDescription('Name of the language.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware(), new Required()),
            (new BoolField('translation_auto_update', 'translationAutoUpdate'))->addFlags(new ApiAware(AdminApiSource::class))->setDescription('Whether the scheduled task keeps the community translations of this language up to date. Enabled by default and only applied to linked languages.'),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            (new ParentAssociationField(self::class, 'id'))->addFlags(new ApiAware())->setDescription('Unique identity of language.'),
            (new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Locale defining regional settings (date, time, number formats)'),
            (new ManyToOneAssociationField('translationCode', 'translation_code_id', LocaleDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Locale used for translating content'),
            (new ChildrenAssociationField(self::class))->addFlags(new ApiAware())->setDescription('Child languages inheriting from this parent language'),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelLanguageDefinition::class, 'language_id', 'sales_channel_id'),

            // api relevant associations, restrict delete
            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'language_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('salesChannelDomains', SalesChannelDomainDefinition::class, 'language_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'language_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'language_id', 'id'))->addFlags(new RestrictDelete()),

            // Translation Associations, not available over sales-channel-api and cascade delete
            (new OneToManyAssociationField('categoryTranslations', CategoryTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('countryStateTranslations', CountryStateTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('countryTranslations', CountryTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('currencyTranslations', CurrencyTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('customerGroupTranslations', CustomerGroupTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('localeTranslations', LocaleTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mediaTranslations', MediaTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productManufacturerTranslations', ProductManufacturerTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productTranslations', ProductTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('unitTranslations', UnitTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('propertyGroupTranslations', PropertyGroupTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('propertyGroupOptionTranslations', PropertyGroupOptionTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('salesChannelTranslations', SalesChannelTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('salesChannelTypeTranslations', SalesChannelTypeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('pluginTranslations', PluginTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productStreamTranslations', ProductStreamTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('stateMachineTranslations', StateMachineTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('stateMachineStateTranslations', StateMachineStateTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mailTemplateTranslations', MailTemplateTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mailHeaderFooterTranslations', MailHeaderFooterTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('numberRangeTypeTranslations', NumberRangeTypeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productSearchKeywords', ProductSearchKeywordDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productKeywordDictionaries', ProductKeywordDictionaryDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mailTemplateTypeTranslations', MailTemplateTypeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('numberRangeTranslations', NumberRangeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('seoUrlTranslations', SeoUrlDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('taxRuleTypeTranslations', TaxRuleTypeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productSortingTranslations', ProductSortingTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('appTranslations', AppTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('actionButtonTranslations', ActionButtonTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('appScriptConditionTranslations', AppScriptConditionTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToOneAssociationField('productSearchConfig', 'id', 'language_id', ProductSearchConfigDefinition::class, false))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('appFlowActionTranslations', AppFlowActionTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
        ]);

        if (!Feature::isActive('v6.8.0.0')) {
            $fields->add((new OneToManyAssociationField('importExportProfileTranslations', ImportExportProfileTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()));
        }

        return $fields;
    }
}
