<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Helper;

use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCategoryTree\BlogCategoryTreeDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCustomFieldSet\BlogCustomFieldSetDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogKeywordDictionary\BlogKeywordDictionaryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword\BlogSearchKeywordDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTag\BlogTagDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingDefinition;
use Contena\Core\Content\Category\Aggregate\CategoryTag\CategoryTagDefinition;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Contena\Core\Content\Cms\CmsPageDefinition;
use Contena\Core\Content\BlogStream\Aggregate\BlogStreamFilter\BlogStreamFilterDefinition;
use Contena\Core\Content\BlogStream\Aggregate\BlogStreamTranslation\BlogStreamTranslationDefinition;
use Contena\Core\Content\BlogStream\BlogStreamDefinition;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileDefinition;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Contena\Core\Content\ImportExport\ImportExportProfileDefinition;
use Contena\Core\Content\ImportExport\ImportExportProfileTranslationDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation\MailTemplateTypeTranslationDefinition;
use Contena\Core\Content\MailTemplate\MailTemplateDefinition;
use Contena\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderDefinition;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Contena\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationDefinition;
use Contena\Core\Content\Media\Aggregate\MediaFolderConfigurationMediaThumbnailSize\MediaFolderConfigurationMediaThumbnailSizeDefinition;
use Contena\Core\Content\Media\Aggregate\MediaTag\MediaTagDefinition;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Contena\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeDefinition;
use Contena\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Contena\Core\Content\Rule\RuleDefinition;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateDefinition;
use Contena\Core\Framework\App\Aggregate\ActionButton\ActionButtonDefinition;
use Contena\Core\Framework\App\Aggregate\ActionButtonTranslation\ActionButtonTranslationDefinition;
use Contena\Core\Framework\App\Aggregate\AppTranslation\AppTranslationDefinition;
use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\App\Template\TemplateDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Contena\Core\System\AdminUser\Aggregate\AdminUserAccessKey\AdminUserAccessKeyDefinition;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelAnalytics\ChannelAnalyticsDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelCountry\ChannelCountryDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelCurrency\ChannelCurrencyDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelLanguage\ChannelLanguageDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelPaymentMethod\ChannelPaymentMethodDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelShippingMethod\ChannelShippingMethodDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelTranslation\ChannelTranslationDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelTypeTranslation\ChannelTypeTranslationDefinition;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\Currency\CurrencyDefinition;
use Contena\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Contena\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;
use Contena\Core\System\CustomField\CustomFieldDefinition;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;
use Contena\Core\System\Locale\LocaleDefinition;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeChannel\NumberRangeChannelDefinition;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeState\NumberRangeStateDefinition;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeDefinition;
use Contena\Core\System\NumberRange\NumberRangeDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Contena\Core\System\StateMachine\StateMachineDefinition;
use Contena\Core\System\StateMachine\StateMachineTranslationDefinition;
use Contena\Core\System\SystemConfig\SystemConfigDefinition;
use Contena\Core\System\Tag\TagDefinition;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressDefinition;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\System\User\Aggregate\UserGroupRegistrationChannel\UserGroupRegistrationChannelDefinition;
use Contena\Core\System\User\Aggregate\UserGroupTranslation\UserGroupTranslationDefinition;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Contena\Core\System\User\Aggregate\UserTag\UserTagDefinition;
use Contena\Core\System\User\UserDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeChannelDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeMediaDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeTranslationDefinition;
use Contena\Frontend\Theme\ThemeDefinition;
use Contena\Core\System\Integration\IntegrationDefinition;
use Contena\Core\System\Unit\UnitDefinition;

/**
 * @internal
 */
class PermissionCategorization
{
    private const CATEGORY_APP = 'app';
    private const CATEGORY_ADMIN_USER = 'admin_user';
    private const CATEGORY_CATEGORY = 'category';
    private const CATEGORY_CMS = 'cms';
    private const CATEGORY_CUSTOMER = 'user';
    private const CATEGORY_CUSTOM_FIELDS = 'custom_fields';
    private const CATEGORY_DOCUMENTS = 'documents';
    private const CATEGORY_GOOGLE_SHOPPING = 'google_shopping';
    private const CATEGORY_IMPORT_EXPORT = 'import_export';
    private const CATEGORY_MAIL_TEMPLATES = 'mail_templates';
    private const CATEGORY_MEDIA = 'media';
    private const CATEGORY_NEWSLETTER = 'newsletter';
    private const CATEGORY_ORDER = 'order';
    private const CATEGORY_OTHER = 'other';
    private const CATEGORY_PAYMENT = 'payment';
    private const CATEGORY_BLOG = 'blog';
    private const CATEGORY_PROMOTION = 'promotion';
    private const CATEGORY_RULES = 'rules';
    private const CATEGORY_CHANNEL = 'channel';
    private const CATEGORY_SETTINGS = 'settings';
    private const CATEGORY_SOCIAL_SHOPPING = 'social_shopping';
    private const CATEGORY_TAG = 'tag';
    private const CATEGORY_THEME = 'theme';
    private const CATEGORY_ADDITIONAL_PRIVILEGES = 'additional_privileges';

    /**
     * @see ThemeDefinition::ENTITY_NAME
     */
    private const THEME_ENTITY_NAME = 'theme';
    /**
     * @see ThemeTranslationDefinition::ENTITY_NAME
     */
    private const THEME_TRANSLATION_ENTITY_NAME = 'theme_translation';
    /**
     * @see ThemeMediaDefinition::ENTITY_NAME
     */
    private const THEME_MEDIA_ENTITY_NAME = 'theme_media';
    /**
     * @see ThemeChannelDefinition::ENTITY_NAME
     */
    private const THEME_CHANNEL_ENTITY_NAME = 'theme_channel';

    private const PERMISSION_CATEGORIES = [
        self::CATEGORY_ADMIN_USER => [
            IntegrationDefinition::ENTITY_NAME,
            AdminUserDefinition::ENTITY_NAME,
            AdminUserAccessKeyDefinition::ENTITY_NAME,
            AdminUserRecoveryDefinition::ENTITY_NAME,
        ],
        self::CATEGORY_APP => [
            TemplateDefinition::ENTITY_NAME,
            AppDefinition::ENTITY_NAME,
            AppTranslationDefinition::ENTITY_NAME,
            ActionButtonDefinition::ENTITY_NAME,
            ActionButtonTranslationDefinition::ENTITY_NAME,
        ],
        self::CATEGORY_CATEGORY => [
            CategoryDefinition::ENTITY_NAME,
            CategoryTranslationDefinition::ENTITY_NAME,
            CategoryTagDefinition::ENTITY_NAME,
        ],
        self::CATEGORY_CMS => [
            CmsBlockDefinition::ENTITY_NAME,
            CmsPageDefinition::ENTITY_NAME,
            CmsPageTranslationDefinition::ENTITY_NAME,
            CmsSectionDefinition::ENTITY_NAME,
            CmsSlotDefinition::ENTITY_NAME,
            CmsSlotTranslationDefinition::ENTITY_NAME,
        ],
        self::CATEGORY_CUSTOMER => [
            UserDefinition::ENTITY_NAME,
            UserAddressDefinition::ENTITY_NAME,
            UserGroupDefinition::ENTITY_NAME,
            UserGroupTranslationDefinition::ENTITY_NAME,
            UserGroupRegistrationChannelDefinition::ENTITY_NAME,
            UserRecoveryDefinition::ENTITY_NAME,
            UserTagDefinition::ENTITY_NAME,
        ],
        self::CATEGORY_CUSTOM_FIELDS => [
            CustomFieldDefinition::ENTITY_NAME,
            CustomFieldSetDefinition::ENTITY_NAME,
            CustomFieldSetRelationDefinition::ENTITY_NAME,
        ],
        self::CATEGORY_GOOGLE_SHOPPING => [
            'swag_google_shopping_account',
            'swag_google_shopping_ads_account',
            'swag_google_shopping_list_ads_account',
            'swag_google_shopping_category',
            'swag_google_shopping_merchant_account',
        ],
        self::CATEGORY_IMPORT_EXPORT => [
            ImportExportFileDefinition::ENTITY_NAME,
            ImportExportLogDefinition::ENTITY_NAME,
            ImportExportProfileDefinition::ENTITY_NAME,
            ImportExportProfileTranslationDefinition::ENTITY_NAME,
        ],
        self::CATEGORY_MAIL_TEMPLATES => [
            MailHeaderFooterDefinition::ENTITY_NAME,
            MailHeaderFooterTranslationDefinition::ENTITY_NAME,
            MailTemplateDefinition::ENTITY_NAME,
            MailTemplateTranslationDefinition::ENTITY_NAME,
            MailTemplateMediaDefinition::ENTITY_NAME,
            MailTemplateTypeDefinition::ENTITY_NAME,
            MailTemplateTypeTranslationDefinition::ENTITY_NAME,
        ],
        self::CATEGORY_MEDIA => [
            MediaDefinition::ENTITY_NAME,
            MediaTranslationDefinition::ENTITY_NAME,
            MediaDefaultFolderDefinition::ENTITY_NAME,
            MediaFolderDefinition::ENTITY_NAME,
            MediaFolderConfigurationDefinition::ENTITY_NAME,
            MediaFolderConfigurationMediaThumbnailSizeDefinition::ENTITY_NAME,
            MediaTagDefinition::ENTITY_NAME,
            MediaThumbnailDefinition::ENTITY_NAME,
            MediaThumbnailSizeDefinition::ENTITY_NAME,
        ],
        self::CATEGORY_ORDER => [
            OrderDefinition::ENTITY_NAME,
        ],
        self::CATEGORY_BLOG => [
            BlogDefinition::ENTITY_NAME,
            BlogCategoryDefinition::ENTITY_NAME,
            BlogCategoryTreeDefinition::ENTITY_NAME,
            BlogKeywordDictionaryDefinition::ENTITY_NAME,
            BlogMediaDefinition::ENTITY_NAME,
            BlogSearchKeywordDefinition::ENTITY_NAME,
            BlogStreamDefinition::ENTITY_NAME,
            BlogStreamFilterDefinition::ENTITY_NAME,
            BlogStreamTranslationDefinition::ENTITY_NAME,
            BlogTagDefinition::ENTITY_NAME,
            BlogVisibilityDefinition::ENTITY_NAME,
            BlogSortingDefinition::ENTITY_NAME,
            BlogTranslationDefinition::ENTITY_NAME,
            BlogCustomFieldSetDefinition::ENTITY_NAME,
        ],
        self::CATEGORY_RULES => [
            RuleDefinition::ENTITY_NAME,
            RuleConditionDefinition::ENTITY_NAME,
        ],
        self::CATEGORY_CHANNEL => [
            ChannelDefinition::ENTITY_NAME,
            ChannelAnalyticsDefinition::ENTITY_NAME,
            ChannelCountryDefinition::ENTITY_NAME,
            ChannelCurrencyDefinition::ENTITY_NAME,
            ChannelDomainDefinition::ENTITY_NAME,
            ChannelLanguageDefinition::ENTITY_NAME,
            ChannelPaymentMethodDefinition::ENTITY_NAME,
            ChannelShippingMethodDefinition::ENTITY_NAME,
            ChannelTranslationDefinition::ENTITY_NAME,
            ChannelTypeDefinition::ENTITY_NAME,
            ChannelTypeTranslationDefinition::ENTITY_NAME,
        ],
        self::CATEGORY_SETTINGS => [
            CountryDefinition::ENTITY_NAME,
            CountryStateDefinition::ENTITY_NAME,
            CurrencyDefinition::ENTITY_NAME,
            LanguageDefinition::ENTITY_NAME,
            LocaleDefinition::ENTITY_NAME,
            LocaleTranslationDefinition::ENTITY_NAME,
            NumberRangeDefinition::ENTITY_NAME,
            NumberRangeChannelDefinition::ENTITY_NAME,
            NumberRangeStateDefinition::ENTITY_NAME,
            NumberRangeTypeDefinition::ENTITY_NAME,
            SeoUrlDefinition::ENTITY_NAME,
            SeoUrlTemplateDefinition::ENTITY_NAME,
            StateMachineDefinition::ENTITY_NAME,
            StateMachineHistoryDefinition::ENTITY_NAME,
            StateMachineStateDefinition::ENTITY_NAME,
            StateMachineStateTranslationDefinition::ENTITY_NAME,
            StateMachineTransitionDefinition::ENTITY_NAME,
            StateMachineTranslationDefinition::ENTITY_NAME,
            SystemConfigDefinition::ENTITY_NAME,
            UnitDefinition::ENTITY_NAME,
            VersionDefinition::ENTITY_NAME,
        ],
        self::CATEGORY_SOCIAL_SHOPPING => [
            'swag_social_shopping_channel',
            'swag_social_shopping_product_error',
        ],
        self::CATEGORY_TAG => [
            TagDefinition::ENTITY_NAME,
        ],
        self::CATEGORY_THEME => [
            self::THEME_ENTITY_NAME,
            self::THEME_TRANSLATION_ENTITY_NAME,
            self::THEME_MEDIA_ENTITY_NAME,
            self::THEME_CHANNEL_ENTITY_NAME,
        ],
        self::CATEGORY_ADDITIONAL_PRIVILEGES => [
            'additional_privileges',
        ],
    ];

    public static function isInCategory(string $entity, string $category): bool
    {
        if ($category === self::CATEGORY_OTHER) {
            $allCategories = array_merge(...array_values(self::PERMISSION_CATEGORIES));

            return !\in_array($entity, $allCategories, true);
        }

        return \in_array($entity, self::PERMISSION_CATEGORIES[$category], true);
    }

    /**
     * @return string[]
     */
    public static function getCategoryNames(): array
    {
        $categories = array_keys(self::PERMISSION_CATEGORIES);
        $categories[] = self::CATEGORY_OTHER;

        return $categories;
    }
}
