<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelDomain;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetEntity;

class ChannelDomainEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $url;

    protected ?string $snippetSetId = null;

    protected ?SnippetSetEntity $snippetSet = null;

    protected string $channelId;

    protected ?ChannelEntity $channel = null;

    protected string $languageId;

    protected ?LanguageEntity $language = null;

    protected ?ChannelEntity $channelDefaultHreflang = null;

    protected bool $hreflangUseOnlyLocale;

    protected bool $isExternalFrontend = false;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

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

    public function isHreflangUseOnlyLocale(): bool
    {
        return $this->hreflangUseOnlyLocale;
    }

    public function setHreflangUseOnlyLocale(bool $hreflangUseOnlyLocale): void
    {
        $this->hreflangUseOnlyLocale = $hreflangUseOnlyLocale;
    }

    public function getIsExternalFrontend(): bool
    {
        return $this->isExternalFrontend;
    }

    public function setIsExternalFrontend(bool $isExternalFrontend): void
    {
        $this->isExternalFrontend = $isExternalFrontend;
    }

    public function getChannelDefaultHreflang(): ?ChannelEntity
    {
        return $this->channelDefaultHreflang;
    }

    public function setChannelDefaultHreflang(?ChannelEntity $channelDefaultHreflang): void
    {
        $this->channelDefaultHreflang = $channelDefaultHreflang;
    }
}
