<?php declare(strict_types=1);

namespace Contena\Core\System\Language;

use Contena\Core\Content\Blog\Aggregate\BlogKeywordDictionary\BlogKeywordDictionaryCollection;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfig\BlogSearchConfigEntity;
use Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword\BlogSearchKeywordCollection;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationCollection;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingTranslationCollection;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationCollection;
use Contena\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationEntity;
use Contena\Core\Content\ContentStream\Aggregate\ContentStreamTranslation\ContentStreamTranslationCollection;
use Contena\Core\Content\ImportExport\ImportExportProfileTranslationCollection;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Contena\Core\Content\MailTemplate\MailTemplateCollection;
use Contena\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationCollection;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationCollection;
use Contena\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Framework\App\Aggregate\ActionButtonTranslation\ActionButtonTranslationCollection;
use Contena\Core\Framework\App\Aggregate\AppScriptConditionTranslation\AppScriptConditionTranslationCollection;
use Contena\Core\Framework\App\Aggregate\AppTranslation\AppTranslationCollection;
use Contena\Core\Framework\App\Aggregate\CmsBlockTranslation\AppCmsBlockTranslationCollection;
use Contena\Core\Framework\App\Aggregate\FlowActionTranslation\AppFlowActionTranslationCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationCollection;
use Contena\Core\Framework\Struct\Collection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelTranslation\ChannelTranslationCollection;
use Contena\Core\System\Channel\Aggregate\ChannelTypeTranslation\ChannelTypeTranslationCollection;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationCollection;
use Contena\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationCollection;
use Contena\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationCollection;
use Contena\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationCollection;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeTranslation\NumberRangeTranslationCollection;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation\NumberRangeTypeTranslationCollection;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationCollection;
use Contena\Core\System\StateMachine\StateMachineTranslationCollection;
use Contena\Core\System\Tax\Aggregate\TaxRuleTypeTranslation\TaxRuleTypeTranslationCollection;
use Contena\Core\System\TaxProvider\Aggregate\TaxProviderTranslation\TaxProviderTranslationCollection;
use Contena\Core\System\User\Aggregate\UserGroupTranslation\UserGroupTranslationCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @codeCoverageIgnore
 */
class LanguageEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $parentId = null;

    protected string $localeId;

    protected ?string $translationCodeId = null;

    protected ?LocaleEntity $translationCode = null;

    protected string $name;

    protected bool $active;

    protected bool $translationAutoUpdate = true;

    protected ?LocaleEntity $locale = null;

    protected ?LanguageEntity $parent = null;

    protected ?LanguageCollection $children = null;

    protected ?ChannelCollection $channels = null;

    protected ?UserCollection $users = null;

    protected ?ChannelCollection $channelDefaultAssignments = null;

    protected ?CategoryTranslationCollection $categoryTranslations = null;

    protected ?CountryStateTranslationCollection $countryStateTranslations = null;

    protected ?CountryTranslationCollection $countryTranslations = null;

    protected ?CurrencyTranslationCollection $currencyTranslations = null;

    protected ?UserGroupTranslationCollection $userGroupTranslations = null;

    protected ?LocaleTranslationCollection $localeTranslations = null;

    protected ?MediaTranslationCollection $mediaTranslations = null;



    protected ?BlogTranslationCollection $blogTranslations = null;


    protected ?UnitTranslationCollection $unitTranslations = null;

    protected ?PropertyGroupTranslationCollection $propertyGroupTranslations = null;

    protected ?PropertyGroupOptionTranslationCollection $propertyGroupOptionTranslations = null;

    protected ?ChannelTranslationCollection $channelTranslations = null;

    protected ?ChannelTypeTranslationCollection $channelTypeTranslations = null;


    protected ?ChannelDomainCollection $channelDomains = null;

    protected ?PluginTranslationCollection $pluginTranslations = null;

    protected ?ContentStreamTranslationCollection $contentStreamTranslations = null;

    protected ?StateMachineTranslationCollection $stateMachineTranslations = null;

    protected ?StateMachineStateTranslationCollection $stateMachineStateTranslations = null;

    /**
     * @var EntityCollection<CmsPageTranslationEntity>|null
     */
    protected ?EntityCollection $cmsPageTranslations = null;

    /**
     * @var EntityCollection<CmsSlotTranslationEntity>|null
     */
    protected ?EntityCollection $cmsSlotTranslations = null;

    protected ?MailTemplateCollection $mailTemplateTranslations = null;

    protected ?MailHeaderFooterCollection $mailHeaderFooterTranslations = null;





    protected ?NumberRangeTypeTranslationCollection $numberRangeTypeTranslations = null;

    protected ?BlogSearchKeywordCollection $blogSearchKeywords = null;

    protected ?BlogKeywordDictionaryCollection $blogKeywordDictionaries = null;

    protected ?MailTemplateTypeDefinition $mailTemplateTypeTranslations = null;


    protected ?NumberRangeTranslationCollection $numberRangeTranslations = null;


    protected ?SeoUrlCollection $seoUrlTranslations = null;

    protected ?TaxRuleTypeTranslationCollection $taxRuleTypeTranslations = null;


    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    protected ?ImportExportProfileTranslationCollection $importExportProfileTranslations = null;


    protected ?AppTranslationCollection $appTranslations = null;

    protected ?ActionButtonTranslationCollection $actionButtonTranslations = null;

    protected ?BlogSortingTranslationCollection $blogSortingTranslations = null;

    protected ?BlogSearchConfigEntity $blogSearchConfig = null;

    protected ?LandingPageTranslationCollection $landingPageTranslations = null;

    protected ?AppCmsBlockTranslationCollection $appCmsBlockTranslations = null;

    protected ?AppScriptConditionTranslationCollection $appScriptConditionTranslations = null;

    protected ?AppFlowActionTranslationCollection $appFlowActionTranslations = null;

    protected ?TaxProviderTranslationCollection $taxProviderTranslations = null;

    public function getMailHeaderFooterTranslations(): ?MailHeaderFooterCollection
    {
        return $this->mailHeaderFooterTranslations;
    }

    public function setMailHeaderFooterTranslations(MailHeaderFooterCollection $mailHeaderFooterTranslations): void
    {
        $this->mailHeaderFooterTranslations = $mailHeaderFooterTranslations;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getLocaleId(): string
    {
        return $this->localeId;
    }

    public function setLocaleId(string $localeId): void
    {
        $this->localeId = $localeId;
    }

    public function getTranslationCodeId(): ?string
    {
        return $this->translationCodeId;
    }

    public function setTranslationCodeId(?string $translationCodeId): void
    {
        $this->translationCodeId = $translationCodeId;
    }

    public function getTranslationCode(): ?LocaleEntity
    {
        return $this->translationCode;
    }

    public function setTranslationCode(?LocaleEntity $translationCode): void
    {
        $this->translationCode = $translationCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getTranslationAutoUpdate(): bool
    {
        return $this->translationAutoUpdate;
    }

    public function setTranslationAutoUpdate(bool $translationAutoUpdate): void
    {
        $this->translationAutoUpdate = $translationAutoUpdate;
    }

    public function getLocale(): ?LocaleEntity
    {
        return $this->locale;
    }

    public function setLocale(LocaleEntity $locale): void
    {
        $this->locale = $locale;
    }

    public function getParent(): ?LanguageEntity
    {
        return $this->parent;
    }

    public function setParent(LanguageEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?LanguageCollection
    {
        return $this->children;
    }

    public function setChildren(LanguageCollection $children): void
    {
        $this->children = $children;
    }

    public function getCategoryTranslations(): ?CategoryTranslationCollection
    {
        return $this->categoryTranslations;
    }

    public function setCategoryTranslations(CategoryTranslationCollection $categoryTranslations): void
    {
        $this->categoryTranslations = $categoryTranslations;
    }

    public function getCountryStateTranslations(): ?CountryStateTranslationCollection
    {
        return $this->countryStateTranslations;
    }

    public function setCountryStateTranslations(CountryStateTranslationCollection $countryStateTranslations): void
    {
        $this->countryStateTranslations = $countryStateTranslations;
    }

    public function getCountryTranslations(): ?CountryTranslationCollection
    {
        return $this->countryTranslations;
    }

    public function setCountryTranslations(CountryTranslationCollection $countryTranslations): void
    {
        $this->countryTranslations = $countryTranslations;
    }

    public function getCurrencyTranslations(): ?CurrencyTranslationCollection
    {
        return $this->currencyTranslations;
    }

    public function setCurrencyTranslations(CurrencyTranslationCollection $currencyTranslations): void
    {
        $this->currencyTranslations = $currencyTranslations;
    }

    public function getUserGroupTranslations(): ?UserGroupTranslationCollection
    {
        return $this->userGroupTranslations;
    }

    public function setUserGroupTranslations(UserGroupTranslationCollection $userGroupTranslations): void
    {
        $this->userGroupTranslations = $userGroupTranslations;
    }

    public function getLocaleTranslations(): ?LocaleTranslationCollection
    {
        return $this->localeTranslations;
    }

    public function setLocaleTranslations(LocaleTranslationCollection $localeTranslations): void
    {
        $this->localeTranslations = $localeTranslations;
    }

    public function getMediaTranslations(): ?MediaTranslationCollection
    {
        return $this->mediaTranslations;
    }

    public function setMediaTranslations(MediaTranslationCollection $mediaTranslations): void
    {
        $this->mediaTranslations = $mediaTranslations;
    }





    public function getBlogTranslations(): ?BlogTranslationCollection
    {
        return $this->blogTranslations;
    }

    public function setBlogTranslations(BlogTranslationCollection $blogTranslations): void
    {
        $this->blogTranslations = $blogTranslations;
    }



    public function getUnitTranslations(): ?UnitTranslationCollection
    {
        return $this->unitTranslations;
    }

    public function setUnitTranslations(UnitTranslationCollection $unitTranslations): void
    {
        $this->unitTranslations = $unitTranslations;
    }

    public function getChannels(): ?ChannelCollection
    {
        return $this->channels;
    }

    public function setChannels(ChannelCollection $channels): void
    {
        $this->channels = $channels;
    }

    public function getChannelDefaultAssignments(): ?ChannelCollection
    {
        return $this->channelDefaultAssignments;
    }

    public function getUsers(): ?UserCollection
    {
        return $this->users;
    }

    public function setUsers(UserCollection $users): void
    {
        $this->users = $users;
    }

    public function setChannelDefaultAssignments(ChannelCollection $channelDefaultAssignments): void
    {
        $this->channelDefaultAssignments = $channelDefaultAssignments;
    }



    public function getPropertyGroupTranslations(): ?PropertyGroupTranslationCollection
    {
        return $this->propertyGroupTranslations;
    }

    public function setPropertyGroupTranslations(PropertyGroupTranslationCollection $propertyGroupTranslations): void
    {
        $this->propertyGroupTranslations = $propertyGroupTranslations;
    }

    public function getPropertyGroupOptionTranslations(): ?PropertyGroupOptionTranslationCollection
    {
        return $this->propertyGroupOptionTranslations;
    }

    public function setPropertyGroupOptionTranslations(PropertyGroupOptionTranslationCollection $propertyGroupOptionTranslationCollection): void
    {
        $this->propertyGroupOptionTranslations = $propertyGroupOptionTranslationCollection;
    }

    public function getChannelTranslations(): ?ChannelTranslationCollection
    {
        return $this->channelTranslations;
    }

    public function setChannelTranslations(ChannelTranslationCollection $channelTranslations): void
    {
        $this->channelTranslations = $channelTranslations;
    }

    public function getChannelTypeTranslations(): ?ChannelTypeTranslationCollection
    {
        return $this->channelTypeTranslations;
    }

    public function setChannelTypeTranslations(ChannelTypeTranslationCollection $channelTypeTranslations): void
    {
        $this->channelTypeTranslations = $channelTypeTranslations;
    }

    public function getChannelDomains(): ?ChannelDomainCollection
    {
        return $this->channelDomains;
    }

    public function setChannelDomains(ChannelDomainCollection $channelDomains): void
    {
        $this->channelDomains = $channelDomains;
    }

    public function getPluginTranslations(): ?PluginTranslationCollection
    {
        return $this->pluginTranslations;
    }

    public function setPluginTranslations(PluginTranslationCollection $pluginTranslations): void
    {
        $this->pluginTranslations = $pluginTranslations;
    }

    public function getBlogStreamTranslations(): ?ContentStreamTranslationCollection
    {
        return $this->contentStreamTranslations;
    }

    public function setBlogStreamTranslations(ContentStreamTranslationCollection $contentStreamTranslations): void
    {
        $this->contentStreamTranslations = $contentStreamTranslations;
    }

    /**
     * @return StateMachineTranslationCollection|null
     */
    public function getStateMachineTranslations(): ?Collection
    {
        return $this->stateMachineTranslations;
    }

    /**
     * @param StateMachineTranslationCollection $stateMachineTranslations
     */
    public function setStateMachineTranslations(Collection $stateMachineTranslations): void
    {
        $this->stateMachineTranslations = $stateMachineTranslations;
    }

    /**
     * @return StateMachineStateTranslationCollection|null
     */
    public function getStateMachineStateTranslations(): ?Collection
    {
        return $this->stateMachineStateTranslations;
    }

    /**
     * @param StateMachineStateTranslationCollection $stateMachineStateTranslations
     */
    public function setStateMachineStateTranslations(Collection $stateMachineStateTranslations): void
    {
        $this->stateMachineStateTranslations = $stateMachineStateTranslations;
    }

    /**
     * @return EntityCollection<CmsPageTranslationEntity>|null
     */
    public function getCmsPageTranslations(): ?Collection
    {
        return $this->cmsPageTranslations;
    }

    /**
     * @param EntityCollection<CmsPageTranslationEntity> $cmsPageTranslations
     */
    public function setCmsPageTranslations(Collection $cmsPageTranslations): void
    {
        $this->cmsPageTranslations = $cmsPageTranslations;
    }

    /**
     * @return EntityCollection<CmsSlotTranslationEntity>|null
     */
    public function getCmsSlotTranslations(): ?Collection
    {
        return $this->cmsSlotTranslations;
    }

    /**
     * @param EntityCollection<CmsSlotTranslationEntity> $cmsSlotTranslations
     */
    public function setCmsSlotTranslations(Collection $cmsSlotTranslations): void
    {
        $this->cmsSlotTranslations = $cmsSlotTranslations;
    }

    public function getMailTemplateTranslations(): ?MailTemplateCollection
    {
        return $this->mailTemplateTranslations;
    }

    public function setMailTemplateTranslations(MailTemplateCollection $mailTemplateTranslations): void
    {
        $this->mailTemplateTranslations = $mailTemplateTranslations;
    }









    public function getNumberRangeTypeTranslations(): ?NumberRangeTypeTranslationCollection
    {
        return $this->numberRangeTypeTranslations;
    }

    public function setNumberRangeTypeTranslations(NumberRangeTypeTranslationCollection $numberRangeTypeTranslations): void
    {
        $this->numberRangeTypeTranslations = $numberRangeTypeTranslations;
    }

    public function getMailTemplateTypeTranslations(): ?MailTemplateTypeDefinition
    {
        return $this->mailTemplateTypeTranslations;
    }

    public function setMailTemplateTypeTranslations(MailTemplateTypeDefinition $mailTemplateTypeTranslations): void
    {
        $this->mailTemplateTypeTranslations = $mailTemplateTypeTranslations;
    }

    public function getBlogSearchKeywords(): ?BlogSearchKeywordCollection
    {
        return $this->blogSearchKeywords;
    }

    public function setBlogSearchKeywords(BlogSearchKeywordCollection $blogSearchKeywords): void
    {
        $this->blogSearchKeywords = $blogSearchKeywords;
    }

    public function getBlogKeywordDictionaries(): ?BlogKeywordDictionaryCollection
    {
        return $this->blogKeywordDictionaries;
    }

    public function setBlogKeywordDictionaries(BlogKeywordDictionaryCollection $blogKeywordDictionaries): void
    {
        $this->blogKeywordDictionaries = $blogKeywordDictionaries;
    }



    public function getNumberRangeTranslations(): ?NumberRangeTranslationCollection
    {
        return $this->numberRangeTranslations;
    }

    public function setNumberRangeTranslations(NumberRangeTranslationCollection $numberRangeTranslations): void
    {
        $this->numberRangeTranslations = $numberRangeTranslations;
    }



    public function getSeoUrlTranslations(): ?SeoUrlCollection
    {
        return $this->seoUrlTranslations;
    }

    public function setSeoUrlTranslations(SeoUrlCollection $seoUrlTranslations): void
    {
        $this->seoUrlTranslations = $seoUrlTranslations;
    }

    public function getTaxRuleTypeTranslations(): ?TaxRuleTypeTranslationCollection
    {
        return $this->taxRuleTypeTranslations;
    }

    public function setTaxRuleTypeTranslations(TaxRuleTypeTranslationCollection $taxRuleTypeTranslations): void
    {
        $this->taxRuleTypeTranslations = $taxRuleTypeTranslations;
    }



    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    public function getImportExportProfileTranslations(): ?ImportExportProfileTranslationCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->importExportProfileTranslations;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    public function setImportExportProfileTranslations(ImportExportProfileTranslationCollection $importExportProfileTranslations): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $this->importExportProfileTranslations = $importExportProfileTranslations;
    }



    public function getAppTranslations(): ?AppTranslationCollection
    {
        return $this->appTranslations;
    }

    public function setAppTranslations(AppTranslationCollection $appTranslations): void
    {
        $this->appTranslations = $appTranslations;
    }

    public function getActionButtonTranslations(): ?ActionButtonTranslationCollection
    {
        return $this->actionButtonTranslations;
    }

    public function setActionButtonTranslations(ActionButtonTranslationCollection $actionButtonTranslations): void
    {
        $this->actionButtonTranslations = $actionButtonTranslations;
    }

    public function getBlogSortingTranslations(): ?BlogSortingTranslationCollection
    {
        return $this->blogSortingTranslations;
    }

    public function setBlogSortingTranslations(BlogSortingTranslationCollection $blogSortingTranslations): void
    {
        $this->blogSortingTranslations = $blogSortingTranslations;
    }

    public function getBlogSearchConfig(): ?BlogSearchConfigEntity
    {
        return $this->blogSearchConfig;
    }

    public function setBlogSearchConfig(BlogSearchConfigEntity $blogSearchConfig): void
    {
        $this->blogSearchConfig = $blogSearchConfig;
    }

    public function getLandingPageTranslations(): ?LandingPageTranslationCollection
    {
        return $this->landingPageTranslations;
    }

    public function setLandingPageTranslations(LandingPageTranslationCollection $landingPageTranslations): void
    {
        $this->landingPageTranslations = $landingPageTranslations;
    }

    public function getAppCmsBlockTranslations(): ?AppCmsBlockTranslationCollection
    {
        return $this->appCmsBlockTranslations;
    }

    public function setAppCmsBlockTranslations(AppCmsBlockTranslationCollection $appCmsBlockTranslations): void
    {
        $this->appCmsBlockTranslations = $appCmsBlockTranslations;
    }

    public function getAppScriptConditionTranslations(): ?AppScriptConditionTranslationCollection
    {
        return $this->appScriptConditionTranslations;
    }

    public function setAppScriptConditionTranslations(AppScriptConditionTranslationCollection $appScriptConditionTranslations): void
    {
        $this->appScriptConditionTranslations = $appScriptConditionTranslations;
    }

    public function getAppFlowActionTranslations(): ?AppFlowActionTranslationCollection
    {
        return $this->appFlowActionTranslations;
    }

    public function setAppFlowActionTranslations(AppFlowActionTranslationCollection $appFlowActionTranslations): void
    {
        $this->appFlowActionTranslations = $appFlowActionTranslations;
    }

    public function getApiAlias(): string
    {
        return 'language';
    }

    public function getTaxProviderTranslations(): ?TaxProviderTranslationCollection
    {
        return $this->taxProviderTranslations;
    }

    public function setTaxProviderTranslations(TaxProviderTranslationCollection $taxProviderTranslations): void
    {
        $this->taxProviderTranslations = $taxProviderTranslations;
    }
}
