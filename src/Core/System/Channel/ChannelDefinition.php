<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\System\User\Aggregate\UserGroupRegistrationChannel\UserGroupRegistrationChannelDefinition;
use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistDefinition;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Cms\CmsPageDefinition;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageChannel\LandingPageChannelDefinition;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Seo\MainCategory\MainCategoryDefinition;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateDefinition;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\Currency\CurrencyDefinition;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeChannel\NumberRangeChannelDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelAnalytics\ChannelAnalyticsDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelCountry\ChannelCountryDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelCurrency\ChannelCurrencyDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelLanguage\ChannelLanguageDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelPaymentMethod\ChannelPaymentMethodDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelShippingMethod\ChannelShippingMethodDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelTranslation\ChannelTranslationDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeDefinition;
use Contena\Core\System\SystemConfig\SystemConfigDefinition;

/**
 * @codeCoverageIgnore
 */
class ChannelDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'channel';
    final public const CALCULATION_TYPE_VERTICAL = 'vertical';
    final public const CALCULATION_TYPE_HORIZONTAL = 'horizontal';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ChannelCollection::class;
    }

    public function getEntityClass(): string
    {
        return ChannelEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'taxCalculationType' => self::CALCULATION_TYPE_HORIZONTAL,
            'homeEnabled' => true,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of sales channel.'),
            (new FkField('type_id', 'typeId', ChannelTypeDefinition::class))->addFlags(new Required())->setDescription('Unique identity of type.'),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of language used.'),
            (new FkField('user_group_id', 'userGroupId', UserGroupDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of user group.'),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of currency used.'),
            (new FkField('country_id', 'countryId', CountryDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of country.'),
            (new FkField('analytics_id', 'analyticsId', ChannelAnalyticsDefinition::class))->setDescription('Unique identity of country.'),

            (new FkField('navigation_category_id', 'navigationCategoryId', CategoryDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of navigation category.'),
            (new ReferenceVersionField(CategoryDefinition::class, 'navigation_category_version_id'))->addFlags(new ApiAware(), new Required()),
            (new IntField('navigation_category_depth', 'navigationCategoryDepth', 1))->addFlags(new ApiAware())->setDescription('It determines the number of levels of subcategories in the frontend category menu.'),
            (new FkField('footer_category_id', 'footerCategoryId', CategoryDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of footer category.'),
            (new ReferenceVersionField(CategoryDefinition::class, 'footer_category_version_id'))->addFlags(new ApiAware(), new Required()),
            (new FkField('service_category_id', 'serviceCategoryId', CategoryDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of service category.'),
            (new ReferenceVersionField(CategoryDefinition::class, 'service_category_version_id'))->addFlags(new ApiAware(), new Required()),
            (new FkField('mail_header_footer_id', 'mailHeaderFooterId', MailHeaderFooterDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of mail header and footer.'),
            (new FkField('hreflang_default_domain_id', 'hreflangDefaultDomainId', ChannelDomainDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of hreflangDefaultDomain.'),
            (new TimeZoneField('business_time_zone', 'businessTimeZone'))->addFlags(new ApiAware(), new Since('6.7.13.0'))->setDescription('Business timezone used for sales-channel-specific rendering.'),
            (new TranslatedField('name'))->addFlags(new ApiAware()),
            (new StringField('short_name', 'shortName'))->addFlags(new ApiAware())->setDescription('A short name for sales channel.'),
            (new StringField('tax_calculation_type', 'taxCalculationType'))->addFlags(new ApiAware())->setDescription('Tax calculation types are `horizontal` and `vertical`.'),
            (new StringField('access_key', 'accessKey'))->addFlags(new Required())->setDescription('Access key to store api.'),
            (new JsonField('configuration', 'configuration'))->addFlags(new ApiAware())->setDescription('Internal field.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the sales channel is enabled.'),
            (new BoolField('hreflang_active', 'hreflangActive'))->addFlags(new ApiAware())->setDescription('When set to true, the sales channel pages are available in different languages.'),
            (new BoolField('maintenance', 'maintenance'))->addFlags(new ApiAware())->setDescription('When `true`, it indicates that the sales channel is undergoing maintenance, and shopping is temporarily unavailable during this period.'),
            (new ListField('maintenance_ip_allowlist', 'maintenanceIpAllowlist', StringField::class))->setDescription('List of IP addresses allowed to access the sales channel while the maintenance mode is active.'),
            // @deprecated tag:v6.8.0 - Will be removed, use `maintenanceIpAllowlist` instead.
            (new ListField('maintenance_ip_whitelist', 'maintenanceIpWhitelist', StringField::class))->addFlags(new Deprecated('v6.7.13.0', 'v6.8.0.0', 'maintenanceIpAllowlist'))->setDescription('List of IP addresses used when the maintenance mode is active.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new TranslationsAssociationField(ChannelTranslationDefinition::class, 'channel_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('currencies', CurrencyDefinition::class, ChannelCurrencyDefinition::class, 'channel_id', 'currency_id'),
            new ManyToManyAssociationField('languages', LanguageDefinition::class, ChannelLanguageDefinition::class, 'channel_id', 'language_id'),
            new ManyToManyAssociationField('countries', CountryDefinition::class, ChannelCountryDefinition::class, 'channel_id', 'country_id'),
            new ManyToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, ChannelPaymentMethodDefinition::class, 'channel_id', 'payment_method_id'),
            (new ManyToManyIdField('payment_method_ids', 'paymentMethodIds', 'paymentMethods'))->setDescription('Unique identity of payment method.'),
            new ManyToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, ChannelShippingMethodDefinition::class, 'channel_id', 'shipping_method_id'),
            new ManyToOneAssociationField('type', 'type_id', ChannelTypeDefinition::class, 'id', false),
            (new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Default language for the sales channel'),
            new ManyToOneAssociationField('userGroup', 'user_group_id', UserGroupDefinition::class, 'id', false),
            (new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Default currency for the sales channel'),
            (new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Default country for the sales channel'),
            new OneToManyAssociationField('orders', OrderDefinition::class, 'channel_id', 'id'),

            new OneToManyAssociationField('users', UserDefinition::class, 'channel_id', 'id'),

            (new FkField('home_cms_page_id', 'homeCmsPageId', CmsPageDefinition::class))->setDescription('Unique identity of home CMS page.'),
            (new ReferenceVersionField(CmsPageDefinition::class, 'home_cms_page_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('homeCmsPage', 'home_cms_page_id', CmsPageDefinition::class, 'id', false),
            new TranslatedField('homeSlotConfig'),
            new TranslatedField('homeEnabled'),
            new TranslatedField('homeName'),
            new TranslatedField('homeMetaTitle'),
            new TranslatedField('homeMetaDescription'),
            new TranslatedField('homeKeywords'),

            (new OneToManyAssociationField('domains', ChannelDomainDefinition::class, 'channel_id', 'id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Domain URLs configured for the sales channel'),

            (new OneToManyAssociationField('systemConfigs', SystemConfigDefinition::class, 'channel_id'))->addFlags(new CascadeDelete()),
            (new ManyToOneAssociationField('navigationCategory', 'navigation_category_id', CategoryDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Root category for navigation menu'),
            (new ManyToOneAssociationField('footerCategory', 'footer_category_id', CategoryDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Root category for footer navigation'),
            (new ManyToOneAssociationField('serviceCategory', 'service_category_id', CategoryDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Root category for service pages'),
            (new OneToManyAssociationField('blogVisibilities', BlogVisibilityDefinition::class, 'channel_id'))->addFlags(new CascadeDelete()),
            (new OneToOneAssociationField('hreflangDefaultDomain', 'hreflang_default_domain_id', 'id', ChannelDomainDefinition::class, false))->addFlags(new ApiAware()),
            new ManyToOneAssociationField('mailHeaderFooter', 'mail_header_footer_id', MailHeaderFooterDefinition::class, 'id', false),
            new OneToManyAssociationField('newsletterRecipients', NewsletterRecipientDefinition::class, 'channel_id', 'id'),
            (new OneToManyAssociationField('numberRangeChannels', NumberRangeChannelDefinition::class, 'channel_id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('blogReviews', BlogReviewDefinition::class, 'channel_id', 'id'),
            (new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'channel_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('seoUrlTemplates', SeoUrlTemplateDefinition::class, 'channel_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mainCategories', MainCategoryDefinition::class, 'channel_id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('blogExports', BlogExportDefinition::class, 'channel_id', 'id'),
            (new OneToManyAssociationField('channelFiles', ChannelFileDefinition::class, 'channel_id', 'id'))->addFlags(new ApiAware(AdminApiSource::class), new CascadeDelete()),
            (new OneToOneAssociationField('analytics', 'analytics_id', 'id', ChannelAnalyticsDefinition::class, false))->addFlags(new CascadeDelete()),
            new ManyToManyAssociationField('userGroupsRegistrations', UserGroupDefinition::class, UserGroupRegistrationChannelDefinition::class, 'channel_id', 'user_group_id', 'id', 'id'),
            new ManyToManyAssociationField('landingPages', LandingPageDefinition::class, LandingPageChannelDefinition::class, 'channel_id', 'landing_page_id', 'id', 'id'),
            new OneToManyAssociationField('boundUsers', UserDefinition::class, 'bound_channel_id', 'id'),
            (new OneToManyAssociationField('wishlists', UserWishlistDefinition::class, 'channel_id'))->addFlags(new CascadeDelete()),
        ]);

        return $fields;
    }
}
