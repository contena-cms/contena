<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppTranslation;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Language\LanguageEntity;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
class AppTranslationEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $label = null;

    protected ?string $description = null;

    protected ?string $privacyPolicyExtensions = null;

    protected string $appId;

    protected string $languageId;

    protected ?AppEntity $app = null;

    protected ?LanguageEntity $language = null;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPrivacyPolicyExtensions(): ?string
    {
        return $this->privacyPolicyExtensions;
    }

    public function setPrivacyPolicyExtensions(?string $privacyPolicyExtensions): void
    {
        $this->privacyPolicyExtensions = $privacyPolicyExtensions;
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getApp(): ?AppEntity
    {
        return $this->app;
    }

    public function setApp(?AppEntity $app): void
    {
        $this->app = $app;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(?LanguageEntity $language): void
    {
        $this->language = $language;
    }
}
