<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrl;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Language\LanguageEntity;

class SeoUrlEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected ?string $channelId = null;

    protected string $languageId;

    protected string $routeName;

    protected string $foreignKey;

    protected string $pathInfo;

    protected string $seoPathInfo;

    protected ?bool $isCanonical = null;

    protected bool $isModified;

    protected bool $isDeleted;

    protected ?ChannelEntity $channel = null;

    protected ?LanguageEntity $language = null;

    protected string $url;

    protected ?string $error = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getChannelId(): ?string
    {
        return $this->channelId;
    }

    public function setChannelId(?string $channelId): void
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

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function setRouteName(string $routeName): void
    {
        $this->routeName = $routeName;
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function setForeignKey(string $foreignKey): void
    {
        $this->foreignKey = $foreignKey;
    }

    public function getPathInfo(): string
    {
        return $this->pathInfo;
    }

    public function setPathInfo(string $pathInfo): void
    {
        $this->pathInfo = $pathInfo;
    }

    public function getSeoPathInfo(): string
    {
        return $this->seoPathInfo;
    }

    public function setSeoPathInfo(string $seoPathInfo): void
    {
        $this->seoPathInfo = $seoPathInfo;
    }

    public function getIsCanonical(): ?bool
    {
        return $this->isCanonical;
    }

    public function setIsCanonical(?bool $isCanonical): void
    {
        $this->isCanonical = $isCanonical;
    }

    public function getIsModified(): bool
    {
        return $this->isModified;
    }

    public function setIsModified(bool $isModified): void
    {
        $this->isModified = $isModified;
    }

    public function getIsDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): void
    {
        $this->isDeleted = $isDeleted;
    }

    public function getChannel(): ?ChannelEntity
    {
        return $this->channel;
    }

    public function setChannel(ChannelEntity $channel): void
    {
        $this->channel = $channel;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }

    /**
     * The error property will be set in the runtime and is not a field in the seo_url table.
     * It is used for the url generation in a json serialized entity.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * The error property will be set in the runtime and is not a field in the seo_url table.
     * It is used for the url generation in a json serialized entity.
     */
    public function setError(?string $error): void
    {
        $this->error = $error;
    }
}
