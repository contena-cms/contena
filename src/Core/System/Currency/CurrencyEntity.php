<?php declare(strict_types=1);

namespace Contena\Core\System\Currency;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingCollection;
use Contena\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @codeCoverageIgnore
 */
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


    protected ?ChannelCollection $channels = null;

    protected ?ChannelCollection $channelDefaultAssignments = null;

    protected ?ChannelDomainCollection $channelDomains = null;


    protected ?bool $isSystemDefault = null;


    protected ?CurrencyCountryRoundingCollection $countryRoundings = null;

    protected CashRoundingConfig $itemRounding;

    protected CashRoundingConfig $totalRounding;

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

    public function setChannelDefaultAssignments(ChannelCollection $channelDefaultAssignments): void
    {
        $this->channelDefaultAssignments = $channelDefaultAssignments;
    }

    public function getChannelDomains(): ?ChannelDomainCollection
    {
        return $this->channelDomains;
    }

    public function setChannelDomains(ChannelDomainCollection $channelDomains): void
    {
        $this->channelDomains = $channelDomains;
    }

    public function getIsSystemDefault(): ?bool
    {
        return $this->isSystemDefault;
    }

    public function setIsSystemDefault(bool $isSystemDefault): void
    {
        $this->isSystemDefault = $isSystemDefault;
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
}
