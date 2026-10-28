<?php declare(strict_types=1);

namespace Contena\Core\System\Currency;

use Contena\Core\Content\ProductExport\ProductExportCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingCollection;
use Contena\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationCollection;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Contena\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class CurrencyEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $isoCode;

    protected float $factor;

    protected string $symbol;

    protected ?string $shortName = null;

    protected ?string $name = null;

    protected int $position;

    protected ?CurrencyTranslationCollection $translations = null;


    protected ?SalesChannelCollection $salesChannels = null;

    protected ?SalesChannelCollection $salesChannelDefaultAssignments = null;

    protected ?SalesChannelDomainCollection $salesChannelDomains = null;


    protected ?bool $isSystemDefault = null;

    protected ?ProductExportCollection $productExports = null;

    protected ?CurrencyCountryRoundingCollection $countryRoundings = null;

    protected CashRoundingConfig $itemRounding;

    protected CashRoundingConfig $totalRounding;

    protected ?float $taxFreeFrom = null;

    public function getIsoCode(): string
    {
        return $this->isoCode;
    }

    public function setIsoCode(string $isoCode): void
    {
        $this->isoCode = $isoCode;
    }

    public function getFactor(): float
    {
        return $this->factor;
    }

    public function setFactor(float $factor): void
    {
        $this->factor = $factor;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): void
    {
        $this->symbol = $symbol;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): void
    {
        $this->shortName = $shortName;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getTranslations(): ?CurrencyTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(CurrencyTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }



    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }

    public function getSalesChannelDefaultAssignments(): ?SalesChannelCollection
    {
        return $this->salesChannelDefaultAssignments;
    }

    public function setSalesChannelDefaultAssignments(SalesChannelCollection $salesChannelDefaultAssignments): void
    {
        $this->salesChannelDefaultAssignments = $salesChannelDefaultAssignments;
    }

    public function getSalesChannelDomains(): ?SalesChannelDomainCollection
    {
        return $this->salesChannelDomains;
    }

    public function setSalesChannelDomains(SalesChannelDomainCollection $salesChannelDomains): void
    {
        $this->salesChannelDomains = $salesChannelDomains;
    }

    public function getIsSystemDefault(): ?bool
    {
        return $this->isSystemDefault;
    }

    public function setIsSystemDefault(bool $isSystemDefault): void
    {
        $this->isSystemDefault = $isSystemDefault;
    }



    public function getProductExports(): ?ProductExportCollection
    {
        return $this->productExports;
    }

    public function setProductExports(ProductExportCollection $productExports): void
    {
        $this->productExports = $productExports;
    }

    public function getCountryRoundings(): ?CurrencyCountryRoundingCollection
    {
        return $this->countryRoundings;
    }

    public function setCountryRoundings(CurrencyCountryRoundingCollection $countryRoundings): void
    {
        $this->countryRoundings = $countryRoundings;
    }

    public function getItemRounding(): CashRoundingConfig
    {
        return $this->itemRounding;
    }

    public function setItemRounding(CashRoundingConfig $itemRounding): void
    {
        $this->itemRounding = $itemRounding;
    }

    public function getTotalRounding(): CashRoundingConfig
    {
        return $this->totalRounding;
    }

    public function setTotalRounding(CashRoundingConfig $totalRounding): void
    {
        $this->totalRounding = $totalRounding;
    }

    public function getTaxFreeFrom(): ?float
    {
        return $this->taxFreeFrom;
    }

    public function setTaxFreeFrom(?float $taxFreeFrom): void
    {
        $this->taxFreeFrom = $taxFreeFrom;
    }
}
