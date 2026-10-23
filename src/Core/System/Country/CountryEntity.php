<?php declare(strict_types=1);

namespace Contena\Core\System\Country;

use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Contena\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationCollection;
use Contena\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingCollection;
use Contena\Core\System\Channel\ChannelCollection;

/**
 * @codeCoverageIgnore
 */
class CountryEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $name = null;

    protected ?string $iso = null;

    protected int $position;

    protected bool $active;

    protected bool $shippingAvailable;

    protected ?string $iso3 = null;

    protected bool $displayStateInRegistration;

    protected bool $forceStateInRegistration;

    protected bool $checkVatIdPattern;

    protected ?string $vatIdPattern = null;

    protected ?bool $vatIdRequired = null;

    protected TaxFreeConfig $userTax;

    protected TaxFreeConfig $companyTax;

    protected ?CountryStateCollection $states = null;

    protected ?CountryTranslationCollection $translations = null;

    protected ?OrderAddressCollection $orderAddresses = null;

    protected ?UserAddressCollection $userAddresses = null;

    protected ?ChannelCollection $channelDefaultAssignments = null;

    protected ?ChannelCollection $channels = null;

    protected ?TaxRuleCollection $taxRules = null;

    protected ?CurrencyCountryRoundingCollection $currencyCountryRoundings = null;

    protected bool $postalCodeRequired;

    protected bool $isEu;

    protected bool $checkPostalCodePattern;

    protected bool $checkAdvancedPostalCodePattern;

    protected ?string $advancedPostalCodePattern = null;

    protected ?string $defaultPostalCodePattern = null;

    /**
     * @var list<list<string>>
     */
    protected array $addressFormat;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getIso(): ?string
    {
        return $this->iso;
    }

    public function setIso(?string $iso): void
    {
        $this->iso = $iso;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getShippingAvailable(): bool
    {
        return $this->shippingAvailable;
    }

    public function setShippingAvailable(bool $shippingAvailable): void
    {
        $this->shippingAvailable = $shippingAvailable;
    }

    public function getIso3(): ?string
    {
        return $this->iso3;
    }

    public function setIso3(?string $iso3): void
    {
        $this->iso3 = $iso3;
    }

    public function getDisplayStateInRegistration(): bool
    {
        return $this->displayStateInRegistration;
    }

    public function setDisplayStateInRegistration(bool $displayStateInRegistration): void
    {
        $this->displayStateInRegistration = $displayStateInRegistration;
    }

    public function getForceStateInRegistration(): bool
    {
        return $this->forceStateInRegistration;
    }

    public function setForceStateInRegistration(bool $forceStateInRegistration): void
    {
        $this->forceStateInRegistration = $forceStateInRegistration;
    }

    public function getCheckVatIdPattern(): bool
    {
        return $this->checkVatIdPattern;
    }

    public function setCheckVatIdPattern(bool $checkVatIdPattern): void
    {
        $this->checkVatIdPattern = $checkVatIdPattern;
    }

    public function getVatIdPattern(): ?string
    {
        return $this->vatIdPattern;
    }

    public function setVatIdPattern(?string $vatIdPattern): void
    {
        $this->vatIdPattern = $vatIdPattern;
    }

    public function getStates(): ?CountryStateCollection
    {
        return $this->states;
    }

    public function setStates(CountryStateCollection $states): void
    {
        $this->states = $states;
    }

    public function getTranslations(): ?CountryTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(CountryTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getOrderAddresses(): ?OrderAddressCollection
    {
        return $this->orderAddresses;
    }

    public function setOrderAddresses(OrderAddressCollection $orderAddresses): void
    {
        $this->orderAddresses = $orderAddresses;
    }

    public function getUserAddresses(): ?UserAddressCollection
    {
        return $this->userAddresses;
    }

    public function setUserAddresses(UserAddressCollection $userAddresses): void
    {
        $this->userAddresses = $userAddresses;
    }

    public function getChannelDefaultAssignments(): ?ChannelCollection
    {
        return $this->channelDefaultAssignments;
    }

    public function setChannelDefaultAssignments(ChannelCollection $channelDefaultAssignments): void
    {
        $this->channelDefaultAssignments = $channelDefaultAssignments;
    }

    public function getChannels(): ?ChannelCollection
    {
        return $this->channels;
    }

    public function setChannels(ChannelCollection $channels): void
    {
        $this->channels = $channels;
    }

    public function getTaxRules(): ?TaxRuleCollection
    {
        return $this->taxRules;
    }

    public function setTaxRules(TaxRuleCollection $taxRules): void
    {
        $this->taxRules = $taxRules;
    }

    public function getCurrencyCountryRoundings(): ?CurrencyCountryRoundingCollection
    {
        return $this->currencyCountryRoundings;
    }

    public function setCurrencyCountryRoundings(CurrencyCountryRoundingCollection $currencyCountryRoundings): void
    {
        $this->currencyCountryRoundings = $currencyCountryRoundings;
    }

    public function getVatIdRequired(): bool
    {
        return (bool) $this->vatIdRequired;
    }

    public function setVatIdRequired(bool $vatIdRequired): void
    {
        $this->vatIdRequired = $vatIdRequired;
    }

    public function getUserTax(): TaxFreeConfig
    {
        return $this->userTax;
    }

    public function setUserTax(TaxFreeConfig $userTax): void
    {
        $this->userTax = $userTax;
    }

    public function getCompanyTax(): TaxFreeConfig
    {
        return $this->companyTax;
    }

    public function setCompanyTax(TaxFreeConfig $companyTax): void
    {
        $this->companyTax = $companyTax;
    }

    public function getPostalCodeRequired(): bool
    {
        return $this->postalCodeRequired;
    }

    public function setPostalCodeRequired(bool $postalCodeRequired): void
    {
        $this->postalCodeRequired = $postalCodeRequired;
    }

    public function getCheckPostalCodePattern(): bool
    {
        return $this->checkPostalCodePattern;
    }

    public function setCheckPostalCodePattern(bool $checkPostalCodePattern): void
    {
        $this->checkPostalCodePattern = $checkPostalCodePattern;
    }

    public function getCheckAdvancedPostalCodePattern(): bool
    {
        return $this->checkAdvancedPostalCodePattern;
    }

    public function setCheckAdvancedPostalCodePattern(bool $checkAdvancedPostalCodePattern): void
    {
        $this->checkAdvancedPostalCodePattern = $checkAdvancedPostalCodePattern;
    }

    public function getAdvancedPostalCodePattern(): ?string
    {
        return $this->advancedPostalCodePattern;
    }

    public function setAdvancedPostalCodePattern(?string $advancedPostalCodePattern): void
    {
        $this->advancedPostalCodePattern = $advancedPostalCodePattern;
    }

    /**
     * @return list<list<string>>
     */
    public function getAddressFormat(): array
    {
        return $this->addressFormat;
    }

    /**
     * @param list<list<string>> $addressFormat
     */
    public function setAddressFormat(array $addressFormat): void
    {
        $this->addressFormat = $addressFormat;
    }

    public function setDefaultPostalCodePattern(?string $pattern): void
    {
        $this->defaultPostalCodePattern = $pattern;
    }

    public function getDefaultPostalCodePattern(): ?string
    {
        return $this->defaultPostalCodePattern;
    }

    public function getIsEu(): bool
    {
        return $this->isEu;
    }

    public function setIsEu(bool $isEu): void
    {
        $this->isEu = $isEu;
    }
}
