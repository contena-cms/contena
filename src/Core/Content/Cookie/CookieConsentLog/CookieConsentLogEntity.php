<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\CookieConsentLog;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CookieConsentLogEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $channelId;

    protected string $languageId;

    protected string $consentAction;

    /**
     * @var list<string>
     */
    protected array $acceptedGroups = [];

    protected string $configHash;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
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

    public function getConsentAction(): string
    {
        return $this->consentAction;
    }

    public function setConsentAction(string $consentAction): void
    {
        $this->consentAction = $consentAction;
    }

    /**
     * @return list<string>
     */
    public function getAcceptedGroups(): array
    {
        return $this->acceptedGroups;
    }

    /**
     * @param list<string> $acceptedGroups
     */
    public function setAcceptedGroups(array $acceptedGroups): void
    {
        $this->acceptedGroups = $acceptedGroups;
    }

    public function getConfigHash(): string
    {
        return $this->configHash;
    }

    public function setConfigHash(string $configHash): void
    {
        $this->configHash = $configHash;
    }
}
