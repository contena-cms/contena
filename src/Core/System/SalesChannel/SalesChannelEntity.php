<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel;

use Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Contena\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistCollection;
use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterEntity;
use Contena\Core\Content\MeasurementSystem\MeasurementUnits;
use Contena\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Contena\Core\Content\ProductExport\ProductExportCollection;
use Contena\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\System\Currency\CurrencyEntity;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel\NumberRangeSalesChannelCollection;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileCollection;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationCollection;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeEntity;
use Contena\Core\System\SystemConfig\SystemConfigCollection;

#[Package('discovery')]
class SalesChannelEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $typeId;

    protected string $languageId;

    protected string $currencyId;

    protected string $paymentMethodId;

    protected string $shippingMethodId;

    protected string $countryId;

    protected string $navigationCategoryId;

    protected string $navigationCategoryVersionId;

    protected int $navigationCategoryDepth;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $homeSlotConfig = null;




    protected bool $homeEnabled;

    protected ?string $homeName = null;

    protected ?string $homeMetaTitle = null;

    protected ?string $homeMetaDescription = null;

    protected ?string $homeKeywords = null;

    protected ?string $footerCategoryId = null;

    protected ?string $footerCategoryVersionId = null;

    protected ?string $serviceCategoryId = null;

    protected ?string $serviceCategoryVersionId = null;

    protected ?string $name = null;

    protected ?string $shortName = null;

    protected string $accessKey;

    protected ?CurrencyCollection $currencies = null;

    protected ?LanguageCollection $languages = null;

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

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use $maintenanceIpAllowlist instead.
     *
     * @var list<string>|null
     */
    protected ?array $maintenanceIpWhitelist = null;

    protected string $taxCalculationType;

    protected ?SalesChannelTypeEntity $type = null;

    protected ?CurrencyEntity $currency = null;

    protected ?LanguageEntity $language = null;



    protected ?CountryEntity $country = null;


    protected ?CustomerCollection $customers = null;

    protected ?CountryCollection $countries = null;



    protected ?SalesChannelTranslationCollection $translations = null;

    protected ?SalesChannelDomainCollection $domains = null;

    protected ?SystemConfigCollection $systemConfigs = null;

    protected ?CategoryEntity $navigationCategory = null;

    protected ?CategoryEntity $footerCategory = null;

    protected ?CategoryEntity $serviceCategory = null;

    protected ?ProductVisibilityCollection $productVisibilities = null;

    protected ?string $mailHeaderFooterId = null;

    protected ?NumberRangeSalesChannelCollection $numberRangeSalesChannels = null;

    protected ?MailHeaderFooterEntity $mailHeaderFooter = null;

    protected string $customerGroupId;

    protected ?CustomerGroupEntity $customerGroup = null;





    protected ?SeoUrlCollection $seoUrls = null;

    protected ?SeoUrlTemplateCollection $seoUrlTemplates = null;

    protected ?MainCategoryCollection $mainCategories = null;

    /**
     * @var list<string>|null
     */
    protected ?array $paymentMethodIds = null;

    protected ?ProductExportCollection $productExports = null;

    protected ?SalesChannelFileCollection $salesChannelFiles = null;

    protected bool $hreflangActive;

    protected ?string $hreflangDefaultDomainId = null;

    protected ?SalesChannelDomainEntity $hreflangDefaultDomain = null;

    protected ?string $analyticsId = null;

    protected ?SalesChannelAnalyticsEntity $analytics = null;

    protected ?CustomerGroupCollection $customerGroupsRegistrations = null;

    protected ?CustomerCollection $boundCustomers = null;

    protected ?CustomerWishlistCollection $wishlists = null;


    protected MeasurementUnits $measurementUnits;

    protected ?string $businessTimeZone = null;

    public function getMailHeaderFooter(): ?MailHeaderFooterEntity
    {
        return $this->mailHeaderFooter;
    }

    public function setMailHeaderFooter(?MailHeaderFooterEntity $mailHeaderFooter): void
    {
        $this->mailHeaderFooter = $mailHeaderFooter;
    }

    public function getMailHeaderFooterId(): ?string
    {
        return $this->mailHeaderFooterId;
    }

    public function setMailHeaderFooterId(string $mailHeaderFooterId): void
    {
        $this->mailHeaderFooterId = $mailHeaderFooterId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getShippingMethodId(): string
    {
        return $this->shippingMethodId;
    }

    public function setShippingMethodId(string $shippingMethodId): void
    {
        $this->shippingMethodId = $shippingMethodId;
    }

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function setCountryId(string $countryId): void
    {
        $this->countryId = $countryId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
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

    public function getCurrencies(): ?CurrencyCollection
    {
        return $this->currencies;
    }

    public function setCurrencies(CurrencyCollection $currencies): void
    {
        $this->currencies = $currencies;
    }

    public function getLanguages(): ?LanguageCollection
    {
        return $this->languages;
    }

    public function setLanguages(LanguageCollection $languages): void
    {
        $this->languages = $languages;
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
        $this->maintenanceIpWhitelist = $maintenanceIpAllowlist;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use getMaintenanceIpAllowlist() instead.
     *
     * @return list<string>|null
     */
    public function getMaintenanceIpWhitelist(): ?array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getMaintenanceIpAllowlist()')
        );

        return $this->getMaintenanceIpAllowlist();
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use setMaintenanceIpAllowlist() instead.
     *
     * @param list<string>|null $maintenanceIpWhitelist
     */
    public function setMaintenanceIpWhitelist(?array $maintenanceIpWhitelist): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'setMaintenanceIpAllowlist()')
        );

        $this->setMaintenanceIpAllowlist($maintenanceIpWhitelist);
    }

    public function getCurrency(): ?CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyEntity $currency): void
    {
        $this->currency = $currency;
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



    public function getCustomers(): ?CustomerCollection
    {
        return $this->customers;
    }

    public function setCustomers(CustomerCollection $customers): void
    {
        $this->customers = $customers;
    }

    public function getTypeId(): string
    {
        return $this->typeId;
    }

    public function setTypeId(string $typeId): void
    {
        $this->typeId = $typeId;
    }

    public function getType(): ?SalesChannelTypeEntity
    {
        return $this->type;
    }

    public function setType(SalesChannelTypeEntity $type): void
    {
        $this->type = $type;
    }

    public function getCountries(): ?CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }

    public function getTranslations(): ?SalesChannelTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(SalesChannelTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }





    public function getDomains(): ?SalesChannelDomainCollection
    {
        return $this->domains;
    }

    public function setDomains(SalesChannelDomainCollection $domains): void
    {
        $this->domains = $domains;
    }

    public function getSystemConfigs(): ?SystemConfigCollection
    {
        return $this->systemConfigs;
    }

    public function setSystemConfigs(SystemConfigCollection $systemConfigs): void
    {
        $this->systemConfigs = $systemConfigs;
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

    /**
     * @return array<string, mixed>|null
     */
    public function getHomeSlotConfig(): ?array
    {
        return $this->homeSlotConfig;
    }

    /**
     * @param array<string, mixed>|null $homeSlotConfig
     */
    public function setHomeSlotConfig(?array $homeSlotConfig): void
    {
        $this->homeSlotConfig = $homeSlotConfig;
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

    public function getProductVisibilities(): ?ProductVisibilityCollection
    {
        return $this->productVisibilities;
    }

    public function setProductVisibilities(ProductVisibilityCollection $productVisibilities): void
    {
        $this->productVisibilities = $productVisibilities;
    }

    public function getCustomerGroupId(): string
    {
        return $this->customerGroupId;
    }

    public function setCustomerGroupId(string $customerGroupId): void
    {
        $this->customerGroupId = $customerGroupId;
    }

    public function getCustomerGroup(): ?CustomerGroupEntity
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroupEntity $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }





    public function getNumberRangeSalesChannels(): ?NumberRangeSalesChannelCollection
    {
        return $this->numberRangeSalesChannels;
    }

    public function setNumberRangeSalesChannels(NumberRangeSalesChannelCollection $numberRangeSalesChannels): void
    {
        $this->numberRangeSalesChannels = $numberRangeSalesChannels;
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





    public function getSeoUrls(): ?SeoUrlCollection
    {
        return $this->seoUrls;
    }

    public function setSeoUrls(SeoUrlCollection $seoUrls): void
    {
        $this->seoUrls = $seoUrls;
    }

    public function getSeoUrlTemplates(): ?SeoUrlTemplateCollection
    {
        return $this->seoUrlTemplates;
    }

    public function setSeoUrlTemplates(SeoUrlTemplateCollection $seoUrlTemplates): void
    {
        $this->seoUrlTemplates = $seoUrlTemplates;
    }

    public function getMainCategories(): ?MainCategoryCollection
    {
        return $this->mainCategories;
    }

    public function setMainCategories(MainCategoryCollection $mainCategories): void
    {
        $this->mainCategories = $mainCategories;
    }

    /**
     * @return list<string>|null
     */
    public function getPaymentMethodIds(): ?array
    {
        return $this->paymentMethodIds;
    }

    /**
     * @param list<string> $paymentMethodIds
     */
    public function setPaymentMethodIds(array $paymentMethodIds): void
    {
        $this->paymentMethodIds = $paymentMethodIds;
    }

    public function getProductExports(): ?ProductExportCollection
    {
        return $this->productExports;
    }

    public function setProductExports(ProductExportCollection $productExports): void
    {
        $this->productExports = $productExports;
    }

    public function getSalesChannelFiles(): ?SalesChannelFileCollection
    {
        return $this->salesChannelFiles;
    }

    public function setSalesChannelFiles(SalesChannelFileCollection $salesChannelFiles): void
    {
        $this->salesChannelFiles = $salesChannelFiles;
    }

    public function getNavigationCategoryDepth(): int
    {
        return $this->navigationCategoryDepth;
    }

    public function setNavigationCategoryDepth(int $navigationCategoryDepth): void
    {
        $this->navigationCategoryDepth = $navigationCategoryDepth;
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

    public function getHreflangDefaultDomain(): ?SalesChannelDomainEntity
    {
        return $this->hreflangDefaultDomain;
    }

    public function setHreflangDefaultDomain(?SalesChannelDomainEntity $hreflangDefaultDomain): void
    {
        $this->hreflangDefaultDomain = $hreflangDefaultDomain;
    }

    public function getAnalyticsId(): ?string
    {
        return $this->analyticsId;
    }

    public function setAnalyticsId(?string $analyticsId): void
    {
        $this->analyticsId = $analyticsId;
    }

    public function getAnalytics(): ?SalesChannelAnalyticsEntity
    {
        return $this->analytics;
    }

    public function setAnalytics(?SalesChannelAnalyticsEntity $analytics): void
    {
        $this->analytics = $analytics;
    }

    public function getTaxCalculationType(): string
    {
        return $this->taxCalculationType;
    }

    public function setTaxCalculationType(string $taxCalculationType): void
    {
        $this->taxCalculationType = $taxCalculationType;
    }

    public function getCustomerGroupsRegistrations(): ?CustomerGroupCollection
    {
        return $this->customerGroupsRegistrations;
    }

    public function setCustomerGroupsRegistrations(CustomerGroupCollection $customerGroupsRegistrations): void
    {
        $this->customerGroupsRegistrations = $customerGroupsRegistrations;
    }

    public function getBoundCustomers(): ?CustomerCollection
    {
        return $this->boundCustomers;
    }

    public function setBoundCustomers(CustomerCollection $boundCustomers): void
    {
        $this->boundCustomers = $boundCustomers;
    }

    public function getWishlists(): ?CustomerWishlistCollection
    {
        return $this->wishlists;
    }

    public function setWishlists(CustomerWishlistCollection $wishlists): void
    {
        $this->wishlists = $wishlists;
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

    public function getMeasurementUnits(): MeasurementUnits
    {
        return $this->measurementUnits;
    }

    public function setMeasurementUnits(MeasurementUnits $measurementUnits): void
    {
        $this->measurementUnits = $measurementUnits;
    }

    public function getBusinessTimeZone(): ?string
    {
        return $this->businessTimeZone;
    }

    public function setBusinessTimeZone(?string $businessTimeZone): void
    {
        $this->businessTimeZone = $businessTimeZone;
    }
}
