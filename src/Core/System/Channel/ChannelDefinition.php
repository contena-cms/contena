<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\Aggregate\ChannelAnalytics\ChannelAnalyticsDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelCountry\ChannelCountryDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelLanguage\ChannelLanguageDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelTranslation\ChannelTranslationDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeDefinition;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupDefinition;
use Contena\Core\System\Member\Aggregate\MemberGroupRegistrationChannel\MemberGroupRegistrationChannelDefinition;
use Contena\Core\System\Member\MemberDefinition;

class ChannelDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'channel';

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
            'homeEnabled' => true,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of channel.'),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned channel.'),
            new FkField('type_id', 'typeId', ChannelTypeDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of channel type.'),
            new FkField('language_id', 'languageId', LanguageDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the default language.'),
            new FkField('country_id', 'countryId', CountryDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the default country.'),
            new FkField('member_group_id', 'memberGroupId', MemberGroupDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the default member group.'),
            new FkField('navigation_category_id', 'navigationCategoryId', CategoryDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of navigation category.'),
            new ReferenceVersionField(CategoryDefinition::class, 'navigation_category_version_id')->addFlags(new ApiAware(), new Required()),
            new IntField('navigation_category_depth', 'navigationCategoryDepth', 1)->addFlags(new ApiAware())->setDescription('It determines the number of levels of subcategories in the frontend category menu.'),
            new FkField('footer_category_id', 'footerCategoryId', CategoryDefinition::class)->addFlags(new ApiAware())->setDescription('Unique identity of footer category.'),
            new ReferenceVersionField(CategoryDefinition::class, 'footer_category_version_id')->addFlags(new ApiAware(), new Required()),
            new FkField('service_category_id', 'serviceCategoryId', CategoryDefinition::class)->addFlags(new ApiAware())->setDescription('Unique identity of service category.'),
            new ReferenceVersionField(CategoryDefinition::class, 'service_category_version_id')->addFlags(new ApiAware(), new Required()),
            new FkField('mail_header_footer_id', 'mailHeaderFooterId', MailHeaderFooterDefinition::class)->addFlags(new ApiAware())->setDescription('Unique identity of mail header and footer.'),
            new FkField('analytics_id', 'analyticsId', ChannelAnalyticsDefinition::class)->addFlags(new ApiAware())->setDescription('Unique identity of channel analytics.'),
            new FkField('hreflang_default_domain_id', 'hreflangDefaultDomainId', ChannelDomainDefinition::class)->addFlags(new ApiAware())->setDescription('Unique identity of hreflang default domain.'),
            new TranslatedField('name')->addFlags(new ApiAware()),
            new TranslatedField('homeEnabled'),
            new TranslatedField('homeName'),
            new TranslatedField('homeMetaTitle'),
            new TranslatedField('homeMetaDescription'),
            new TranslatedField('homeKeywords'),
            new StringField('short_name', 'shortName')->addFlags(new ApiAware())->setDescription('A short name for channel.'),
            new StringField('access_key', 'accessKey')->addFlags(new Required())->setDescription('Access key to Channel API.'),
            new JsonField('configuration', 'configuration')->addFlags(new ApiAware())->setDescription('Internal field.'),
            new BoolField('active', 'active')->addFlags(new ApiAware())->setDescription('When true, the channel is enabled.'),
            new BoolField('hreflang_active', 'hreflangActive')->addFlags(new ApiAware())->setDescription('When true, channel pages are available in different languages.'),
            new BoolField('maintenance', 'maintenance')->addFlags(new ApiAware())->setDescription('When true, the channel is undergoing maintenance.'),
            new ListField('maintenance_ip_allowlist', 'maintenanceIpAllowlist', StringField::class)->setDescription('IP addresses allowed to access the channel during maintenance.'),
            new TimeZoneField('business_time_zone', 'businessTimeZone')->addFlags(new ApiAware())->setDescription('Business timezone used for channel-specific rendering.'),
            new TranslatedField('customFields')->addFlags(new ApiAware()),
            new TranslationsAssociationField(ChannelTranslationDefinition::class, 'channel_id')->addFlags(new Required()),
            new ManyToManyAssociationField('languages', LanguageDefinition::class, ChannelLanguageDefinition::class, 'channel_id', 'language_id'),
            new ManyToManyAssociationField('countries', CountryDefinition::class, ChannelCountryDefinition::class, 'channel_id', 'country_id'),
            new ManyToOneAssociationField('type', 'type_id', ChannelTypeDefinition::class, 'id', false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', false)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('memberGroup', 'member_group_id', MemberGroupDefinition::class, 'id', false),
            new ManyToOneAssociationField('navigationCategory', 'navigation_category_id', CategoryDefinition::class, 'id', false)->addFlags(new ApiAware())->setDescription('Root category for navigation menu.'),
            new ManyToOneAssociationField('footerCategory', 'footer_category_id', CategoryDefinition::class, 'id', false)->addFlags(new ApiAware())->setDescription('Root category for footer navigation.'),
            new ManyToOneAssociationField('serviceCategory', 'service_category_id', CategoryDefinition::class, 'id', false)->addFlags(new ApiAware())->setDescription('Root category for service pages.'),
            new ManyToOneAssociationField('mailHeaderFooter', 'mail_header_footer_id', MailHeaderFooterDefinition::class, 'id', false),
            new OneToManyAssociationField('domains', ChannelDomainDefinition::class, 'channel_id', 'id')->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Domain URLs configured for the channel.'),
            new OneToManyAssociationField('channelFiles', ChannelFileDefinition::class, 'channel_id', 'id')->addFlags(new ApiAware(), new CascadeDelete()),
            new OneToOneAssociationField('hreflangDefaultDomain', 'hreflang_default_domain_id', 'id', ChannelDomainDefinition::class, false)->addFlags(new ApiAware()),
            new OneToOneAssociationField('analytics', 'analytics_id', 'id', ChannelAnalyticsDefinition::class, false)->addFlags(new ApiAware(), new CascadeDelete()),
            new OneToManyAssociationField('members', MemberDefinition::class, 'channel_id', 'id'),
            new OneToManyAssociationField('blogVisibilities', BlogVisibilityDefinition::class, 'channel_id')->addFlags(new CascadeDelete()),
            new ManyToManyAssociationField('memberGroupsRegistrations', MemberGroupDefinition::class, MemberGroupRegistrationChannelDefinition::class, 'channel_id', 'member_group_id', 'id', 'id'),
        ]);
    }
}
