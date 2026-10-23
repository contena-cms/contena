<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelDomain;

use Contena\Core\Content\MeasurementSystem\MeasurementUnits;
use Contena\Core\Content\BlogExport\BlogExportCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Currency\CurrencyEntity;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetEntity;

/**
 * @codeCoverageIgnore
 */
class ChannelDomainEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $url;

    protected ?string $currencyId = null;

    protected ?CurrencyEntity $currency = null;

    protected ?string $snippetSetId = null;

    protected ?SnippetSetEntity $snippetSet = null;

    protected string $channelId;

    protected ?ChannelEntity $channel = null;

    protected string $languageId;

    protected ?LanguageEntity $language = null;

    protected MeasurementUnits $measurementUnits;

    protected ?BlogExportCollection $blogExports = null;

    protected ?ChannelEntity $channelDefaultHreflang = null;

    protected bool $hreflangUseOnlyLocale;

    protected bool $isExternalFrontend = false;

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function setChannelId(string $channelId): void
    {
        $this->channelId = $channelId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getChannel(): ?ChannelEntity
    {
        return $this->channel;
    }

    public function setChannel(ChannelEntity $channel): void
    {
        $this->channel = $channel;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }

    public function getCurrencyId(): ?string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(?string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getCurrency(): ?CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(?CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }

    public function getSnippetSetId(): ?string
    {
        return $this->snippetSetId;
    }

    public function setSnippetSetId(?string $snippetSetId): void
    {
        $this->snippetSetId = $snippetSetId;
    }

    public function getSnippetSet(): ?SnippetSetEntity
    {
        return $this->snippetSet;
    }

    public function setSnippetSet(?SnippetSetEntity $snippetSet): void
    {
        $this->snippetSet = $snippetSet;
    }

    public function getBlogExports(): ?BlogExportCollection
    {
        return $this->blogExports;
    }

    public function setBlogExports(BlogExportCollection $blogExports): void
    {
        $this->blogExports = $blogExports;
    }

    public function isHreflangUseOnlyLocale(): bool
    {
        return $this->hreflangUseOnlyLocale;
    }

    public function setHreflangUseOnlyLocale(bool $hreflangUseOnlyLocale): void
    {
        $this->hreflangUseOnlyLocale = $hreflangUseOnlyLocale;
    }

    public function getChannelDefaultHreflang(): ?ChannelEntity
    {
        return $this->channelDefaultHreflang;
    }

    public function setChannelDefaultHreflang(?ChannelEntity $channelDefaultHreflang): void
    {
        $this->channelDefaultHreflang = $channelDefaultHreflang;
    }

    public function getMeasurementUnits(): MeasurementUnits
    {
        return $this->measurementUnits;
    }

    public function setMeasurementUnits(MeasurementUnits $measurementUnits): void
    {
        $this->measurementUnits = $measurementUnits;
    }

    public function getIsExternalFrontend(): bool
    {
        return $this->isExternalFrontend;
    }

    public function setIsExternalFrontend(bool $isExternalFrontend): void
    {
        $this->isExternalFrontend = $isExternalFrontend;
    }
}
