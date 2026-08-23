<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityCollection;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Channel\Aggregate\ChannelAnalytics\ChannelAnalyticsEntity;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileCollection;
use Contena\Core\System\Channel\Aggregate\ChannelTranslation\ChannelTranslationCollection;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeEntity;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\System\Member\MemberCollection;

class ChannelEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $typeId;

    protected string $languageId;

    protected string $countryId;

    protected string $memberGroupId;

    protected string $navigationCategoryId;

    protected string $navigationCategoryVersionId;

    protected int $navigationCategoryDepth;

    protected ?string $footerCategoryId = null;

    protected ?string $footerCategoryVersionId = null;

    protected ?string $serviceCategoryId = null;

    protected ?string $serviceCategoryVersionId = null;

    protected ?string $mailHeaderFooterId = null;

    protected ?string $analyticsId = null;

    protected ?string $name = null;

    protected bool $homeEnabled;

    protected ?string $homeName = null;

    protected ?string $homeMetaTitle = null;

    protected ?string $homeMetaDescription = null;

    protected ?string $homeKeywords = null;

    protected ?string $shortName = null;

    protected string $accessKey;

    /**
     * @var array<mixed>|null
     */
    protected ?array $configuration = null;

    protected bool $active;

    protected bool $maintenance;

    /**
     * @var list<string>|null
     */
    protected ?array $maintenanceIpAllowlist = null;

    protected bool $hreflangActive;

    protected ?string $hreflangDefaultDomainId = null;

    protected ?string $businessTimeZone = null;

    protected ?ChannelTypeEntity $type = null;

    protected ?LanguageEntity $language = null;

    protected ?CountryEntity $country = null;

    protected ?MemberGroupEntity $memberGroup = null;

    protected ?CategoryEntity $navigationCategory = null;

    protected ?CategoryEntity $footerCategory = null;

    protected ?CategoryEntity $serviceCategory = null;

    protected ?MailHeaderFooterEntity $mailHeaderFooter = null;

    protected ?ChannelAnalyticsEntity $analytics = null;

    protected ?LanguageCollection $languages = null;

    protected ?CountryCollection $countries = null;

    protected ?ChannelTranslationCollection $translations = null;

    protected ?ChannelDomainCollection $domains = null;

    protected ?ChannelFileCollection $channelFiles = null;

    protected ?ChannelDomainEntity $hreflangDefaultDomain = null;

    protected ?MemberGroupCollection $memberGroupsRegistrations = null;

    protected ?MemberCollection $members = null;

    protected ?BlogVisibilityCollection $blogVisibilities = null;

    public function getChannelFiles(): ?ChannelFileCollection
    {
        return $this->channelFiles;
    }

    public function setChannelFiles(ChannelFileCollection $channelFiles): void
    {
        $this->channelFiles = $channelFiles;
    }

    public function getTypeId(): string
    {
        return $this->typeId;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function setTypeId(string $typeId): void
    {
        $this->typeId = $typeId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function setCountryId(string $countryId): void
    {
        $this->countryId = $countryId;
    }

    public function getMemberGroupId(): string
    {
        return $this->memberGroupId;
    }

    public function setMemberGroupId(string $memberGroupId): void
    {
        $this->memberGroupId = $memberGroupId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getHomeEnabled(): bool
    {
        return $this->homeEnabled;
    }

    public function setHomeEnabled(bool $homeEnabled): void
    {
        $this->homeEnabled = $homeEnabled;
    }

    public function getHomeName(): ?string
    {
        return $this->homeName;
    }

    public function setHomeName(?string $homeName): void
    {
        $this->homeName = $homeName;
    }

    public function getHomeMetaTitle(): ?string
    {
        return $this->homeMetaTitle;
    }

    public function setHomeMetaTitle(?string $homeMetaTitle): void
    {
        $this->homeMetaTitle = $homeMetaTitle;
    }

    public function getHomeMetaDescription(): ?string
    {
        return $this->homeMetaDescription;
    }

    public function setHomeMetaDescription(?string $homeMetaDescription): void
    {
        $this->homeMetaDescription = $homeMetaDescription;
    }

    public function getHomeKeywords(): ?string
    {
        return $this->homeKeywords;
    }

    public function setHomeKeywords(?string $homeKeywords): void
    {
        $this->homeKeywords = $homeKeywords;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): void
    {
        $this->shortName = $shortName;
    }

    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    public function setAccessKey(string $accessKey): void
    {
        $this->accessKey = $accessKey;
    }

    /**
     * @return array<mixed>|null
     */
    public function getConfiguration(): ?array
    {
        return $this->configuration;
    }

    /**
     * @param array<mixed> $configuration
     */
    public function setConfiguration(array $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function isMaintenance(): bool
    {
        return $this->maintenance;
    }

    public function setMaintenance(bool $maintenance): void
    {
        $this->maintenance = $maintenance;
    }

    /**
     * @return list<string>|null
     */
    public function getMaintenanceIpAllowlist(): ?array
    {
        return $this->maintenanceIpAllowlist;
    }

    /**
     * @param list<string>|null $maintenanceIpAllowlist
     */
    public function setMaintenanceIpAllowlist(?array $maintenanceIpAllowlist): void
    {
        $this->maintenanceIpAllowlist = $maintenanceIpAllowlist;
    }

    public function isHreflangActive(): bool
    {
        return $this->hreflangActive;
    }

    public function setHreflangActive(bool $hreflangActive): void
    {
        $this->hreflangActive = $hreflangActive;
    }

    public function getHreflangDefaultDomainId(): ?string
    {
        return $this->hreflangDefaultDomainId;
    }

    public function setHreflangDefaultDomainId(?string $hreflangDefaultDomainId): void
    {
        $this->hreflangDefaultDomainId = $hreflangDefaultDomainId;
    }

    public function getBusinessTimeZone(): ?string
    {
        return $this->businessTimeZone;
    }

    public function setBusinessTimeZone(?string $businessTimeZone): void
    {
        $this->businessTimeZone = $businessTimeZone;
    }

    public function getType(): ?ChannelTypeEntity
    {
        return $this->type;
    }

    public function setType(ChannelTypeEntity $type): void
    {
        $this->type = $type;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }

    public function getCountry(): ?CountryEntity
    {
        return $this->country;
    }

    public function setCountry(CountryEntity $country): void
    {
        $this->country = $country;
    }

    public function getMemberGroup(): ?MemberGroupEntity
    {
        return $this->memberGroup;
    }

    public function setMemberGroup(MemberGroupEntity $memberGroup): void
    {
        $this->memberGroup = $memberGroup;
    }

    public function getNavigationCategoryId(): string
    {
        return $this->navigationCategoryId;
    }

    public function setNavigationCategoryId(string $navigationCategoryId): void
    {
        $this->navigationCategoryId = $navigationCategoryId;
    }

    public function getNavigationCategory(): ?CategoryEntity
    {
        return $this->navigationCategory;
    }

    public function setNavigationCategory(CategoryEntity $navigationCategory): void
    {
        $this->navigationCategory = $navigationCategory;
    }

    public function getFooterCategoryId(): ?string
    {
        return $this->footerCategoryId;
    }

    public function setFooterCategoryId(string $footerCategoryId): void
    {
        $this->footerCategoryId = $footerCategoryId;
    }

    public function getServiceCategoryId(): ?string
    {
        return $this->serviceCategoryId;
    }

    public function setServiceCategoryId(string $serviceCategoryId): void
    {
        $this->serviceCategoryId = $serviceCategoryId;
    }

    public function getFooterCategory(): ?CategoryEntity
    {
        return $this->footerCategory;
    }

    public function setFooterCategory(CategoryEntity $footerCategory): void
    {
        $this->footerCategory = $footerCategory;
    }

    public function getServiceCategory(): ?CategoryEntity
    {
        return $this->serviceCategory;
    }

    public function setServiceCategory(CategoryEntity $serviceCategory): void
    {
        $this->serviceCategory = $serviceCategory;
    }

    public function getNavigationCategoryDepth(): int
    {
        return $this->navigationCategoryDepth;
    }

    public function setNavigationCategoryDepth(int $navigationCategoryDepth): void
    {
        $this->navigationCategoryDepth = $navigationCategoryDepth;
    }

    public function getNavigationCategoryVersionId(): string
    {
        return $this->navigationCategoryVersionId;
    }

    public function setNavigationCategoryVersionId(string $navigationCategoryVersionId): void
    {
        $this->navigationCategoryVersionId = $navigationCategoryVersionId;
    }

    public function getFooterCategoryVersionId(): ?string
    {
        return $this->footerCategoryVersionId;
    }

    public function setFooterCategoryVersionId(?string $footerCategoryVersionId): void
    {
        $this->footerCategoryVersionId = $footerCategoryVersionId;
    }

    public function getServiceCategoryVersionId(): ?string
    {
        return $this->serviceCategoryVersionId;
    }

    public function setServiceCategoryVersionId(?string $serviceCategoryVersionId): void
    {
        $this->serviceCategoryVersionId = $serviceCategoryVersionId;
    }

    public function getMailHeaderFooterId(): ?string
    {
        return $this->mailHeaderFooterId;
    }

    public function getAnalyticsId(): ?string
    {
        return $this->analyticsId;
    }

    public function setAnalyticsId(?string $analyticsId): void
    {
        $this->analyticsId = $analyticsId;
    }

    public function getAnalytics(): ?ChannelAnalyticsEntity
    {
        return $this->analytics;
    }

    public function setAnalytics(?ChannelAnalyticsEntity $analytics): void
    {
        $this->analytics = $analytics;
    }

    public function setMailHeaderFooterId(string $mailHeaderFooterId): void
    {
        $this->mailHeaderFooterId = $mailHeaderFooterId;
    }

    public function getMailHeaderFooter(): ?MailHeaderFooterEntity
    {
        return $this->mailHeaderFooter;
    }

    public function setMailHeaderFooter(?MailHeaderFooterEntity $mailHeaderFooter): void
    {
        $this->mailHeaderFooter = $mailHeaderFooter;
    }

    public function getLanguages(): ?LanguageCollection
    {
        return $this->languages;
    }

    public function setLanguages(LanguageCollection $languages): void
    {
        $this->languages = $languages;
    }

    public function getCountries(): ?CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }

    public function getTranslations(): ?ChannelTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ChannelTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getDomains(): ?ChannelDomainCollection
    {
        return $this->domains;
    }

    public function setDomains(ChannelDomainCollection $domains): void
    {
        $this->domains = $domains;
    }

    public function getHreflangDefaultDomain(): ?ChannelDomainEntity
    {
        return $this->hreflangDefaultDomain;
    }

    public function setHreflangDefaultDomain(?ChannelDomainEntity $hreflangDefaultDomain): void
    {
        $this->hreflangDefaultDomain = $hreflangDefaultDomain;
    }

    public function getMemberGroupsRegistrations(): ?MemberGroupCollection
    {
        return $this->memberGroupsRegistrations;
    }

    public function setMemberGroupsRegistrations(MemberGroupCollection $memberGroupsRegistrations): void
    {
        $this->memberGroupsRegistrations = $memberGroupsRegistrations;
    }

    public function getMembers(): ?MemberCollection
    {
        return $this->members;
    }

    public function setMembers(MemberCollection $members): void
    {
        $this->members = $members;
    }

    public function getBlogVisibilities(): ?BlogVisibilityCollection
    {
        return $this->blogVisibilities;
    }

    public function setBlogVisibilities(BlogVisibilityCollection $blogVisibilities): void
    {
        $this->blogVisibilities = $blogVisibilities;
    }
}
