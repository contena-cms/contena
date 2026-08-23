<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\CookieConsentConfigVersion;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CookieConsentConfigVersionEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $configHash;

    protected string $channelId;

    protected string $languageId;

    /**
     * @var array<int|string, mixed>
     */
    protected array $cookieGroups = [];

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getConfigHash(): string
    {
        return $this->configHash;
    }

    public function setConfigHash(string $configHash): void
    {
        $this->configHash = $configHash;
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

    /**
     * @return array<int|string, mixed>
     */
    public function getCookieGroups(): array
    {
        return $this->cookieGroups;
    }

    /**
     * @param array<int|string, mixed> $cookieGroups
     */
    public function setCookieGroups(array $cookieGroups): void
    {
        $this->cookieGroups = $cookieGroups;
    }
}
