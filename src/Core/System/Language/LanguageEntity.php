<?php declare(strict_types=1);

namespace Contena\Core\System\Language;

use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation\MailTemplateTypeTranslationCollection;
use Contena\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationCollection;
use Contena\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationCollection;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItemTranslation\DataDictionaryItemTranslationCollection;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryTranslation\DataDictionaryTranslationCollection;
use Contena\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationCollection;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeTranslation\NumberRangeTranslationCollection;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation\NumberRangeTypeTranslationCollection;
use Contena\Core\System\Organization\Aggregate\OrganizationTranslation\OrganizationTranslationCollection;
use Contena\Core\System\Organization\Aggregate\OrganizationUnitTranslation\OrganizationUnitTranslationCollection;
use Contena\Core\System\Position\Aggregate\PositionTranslation\PositionTranslationCollection;
use Contena\Core\System\Region\Aggregate\RegionTranslation\RegionTranslationCollection;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationCollection;
use Contena\Core\System\StateMachine\StateMachineTranslationCollection;

class LanguageEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $parentId = null;

    protected string $localeId;

    protected ?string $translationCodeId = null;

    protected string $name;

    protected bool $active;

    protected bool $translationAutoUpdate = true;

    protected ?LocaleEntity $translationCode = null;

    protected ?LocaleEntity $locale = null;

    protected ?LanguageEntity $parent = null;

    protected ?LanguageCollection $children = null;

    protected ?RegionTranslationCollection $regionTranslations = null;

    protected ?OrganizationTranslationCollection $organizationTranslations = null;

    protected ?OrganizationUnitTranslationCollection $organizationUnitTranslations = null;

    protected ?PositionTranslationCollection $positionTranslations = null;

    protected ?CountryTranslationCollection $countryTranslations = null;

    protected ?DataDictionaryTranslationCollection $dataDictionaryTranslations = null;

    protected ?DataDictionaryItemTranslationCollection $dataDictionaryItemTranslations = null;

    protected ?LocaleTranslationCollection $localeTranslations = null;

    protected ?MailHeaderFooterTranslationCollection $mailHeaderFooterTranslations = null;

    protected ?MailTemplateTranslationCollection $mailTemplateTranslations = null;

    protected ?MailTemplateTypeTranslationCollection $mailTemplateTypeTranslations = null;

    protected ?MediaTranslationCollection $mediaTranslations = null;

    protected ?PluginTranslationCollection $pluginTranslations = null;

    protected ?StateMachineTranslationCollection $stateMachineTranslations = null;

    protected ?StateMachineStateTranslationCollection $stateMachineStateTranslations = null;

    protected ?NumberRangeTypeTranslationCollection $numberRangeTypeTranslations = null;

    protected ?NumberRangeTranslationCollection $numberRangeTranslations = null;

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

    public function isTranslationAutoUpdate(): bool
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

    public function getRegionTranslations(): ?RegionTranslationCollection
    {
        return $this->regionTranslations;
    }

    public function setRegionTranslations(RegionTranslationCollection $translations): void
    {
        $this->regionTranslations = $translations;
    }

    public function getOrganizationTranslations(): ?OrganizationTranslationCollection
    {
        return $this->organizationTranslations;
    }

    public function setOrganizationTranslations(OrganizationTranslationCollection $translations): void
    {
        $this->organizationTranslations = $translations;
    }

    public function getOrganizationUnitTranslations(): ?OrganizationUnitTranslationCollection
    {
        return $this->organizationUnitTranslations;
    }

    public function setOrganizationUnitTranslations(OrganizationUnitTranslationCollection $translations): void
    {
        $this->organizationUnitTranslations = $translations;
    }

    public function getPositionTranslations(): ?PositionTranslationCollection
    {
        return $this->positionTranslations;
    }

    public function setPositionTranslations(PositionTranslationCollection $translations): void
    {
        $this->positionTranslations = $translations;
    }

    public function getCountryTranslations(): ?CountryTranslationCollection
    {
        return $this->countryTranslations;
    }

    public function setCountryTranslations(CountryTranslationCollection $translations): void
    {
        $this->countryTranslations = $translations;
    }

    public function getDataDictionaryTranslations(): ?DataDictionaryTranslationCollection
    {
        return $this->dataDictionaryTranslations;
    }

    public function setDataDictionaryTranslations(DataDictionaryTranslationCollection $translations): void
    {
        $this->dataDictionaryTranslations = $translations;
    }

    public function getDataDictionaryItemTranslations(): ?DataDictionaryItemTranslationCollection
    {
        return $this->dataDictionaryItemTranslations;
    }

    public function setDataDictionaryItemTranslations(DataDictionaryItemTranslationCollection $translations): void
    {
        $this->dataDictionaryItemTranslations = $translations;
    }

    public function getLocaleTranslations(): ?LocaleTranslationCollection
    {
        return $this->localeTranslations;
    }

    public function setLocaleTranslations(LocaleTranslationCollection $translations): void
    {
        $this->localeTranslations = $translations;
    }

    public function getMailHeaderFooterTranslations(): ?MailHeaderFooterTranslationCollection
    {
        return $this->mailHeaderFooterTranslations;
    }

    public function setMailHeaderFooterTranslations(MailHeaderFooterTranslationCollection $translations): void
    {
        $this->mailHeaderFooterTranslations = $translations;
    }

    public function getMailTemplateTranslations(): ?MailTemplateTranslationCollection
    {
        return $this->mailTemplateTranslations;
    }

    public function setMailTemplateTranslations(MailTemplateTranslationCollection $translations): void
    {
        $this->mailTemplateTranslations = $translations;
    }

    public function getMailTemplateTypeTranslations(): ?MailTemplateTypeTranslationCollection
    {
        return $this->mailTemplateTypeTranslations;
    }

    public function setMailTemplateTypeTranslations(MailTemplateTypeTranslationCollection $translations): void
    {
        $this->mailTemplateTypeTranslations = $translations;
    }

    public function getMediaTranslations(): ?MediaTranslationCollection
    {
        return $this->mediaTranslations;
    }

    public function setMediaTranslations(MediaTranslationCollection $translations): void
    {
        $this->mediaTranslations = $translations;
    }

    public function getPluginTranslations(): ?PluginTranslationCollection
    {
        return $this->pluginTranslations;
    }

    public function setPluginTranslations(PluginTranslationCollection $translations): void
    {
        $this->pluginTranslations = $translations;
    }

    public function getStateMachineTranslations(): ?StateMachineTranslationCollection
    {
        return $this->stateMachineTranslations;
    }

    public function setStateMachineTranslations(StateMachineTranslationCollection $translations): void
    {
        $this->stateMachineTranslations = $translations;
    }

    public function getStateMachineStateTranslations(): ?StateMachineStateTranslationCollection
    {
        return $this->stateMachineStateTranslations;
    }

    public function setStateMachineStateTranslations(StateMachineStateTranslationCollection $translations): void
    {
        $this->stateMachineStateTranslations = $translations;
    }

    public function getNumberRangeTypeTranslations(): ?NumberRangeTypeTranslationCollection
    {
        return $this->numberRangeTypeTranslations;
    }

    public function setNumberRangeTypeTranslations(NumberRangeTypeTranslationCollection $translations): void
    {
        $this->numberRangeTypeTranslations = $translations;
    }

    public function getNumberRangeTranslations(): ?NumberRangeTranslationCollection
    {
        return $this->numberRangeTranslations;
    }

    public function setNumberRangeTranslations(NumberRangeTranslationCollection $translations): void
    {
        $this->numberRangeTranslations = $translations;
    }
}
