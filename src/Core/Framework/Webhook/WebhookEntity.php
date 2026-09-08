<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @codeCoverageIgnore
 */
class WebhookEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected string $eventName;

    protected string $url;

    protected bool $onlyLiveVersion;

    protected ?string $appId = null;

    protected ?AppEntity $app = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function setEventName(string $eventName): void
    {
        $this->eventName = $eventName;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getOnlyLiveVersion(): bool
    {
        return $this->onlyLiveVersion;
    }

    public function setOnlyLiveVersion(bool $onlyLiveVersion): void
    {
        $this->onlyLiveVersion = $onlyLiveVersion;
    }

    public function getAppId(): ?string
    {
        return $this->appId;
    }

    public function setAppId(?string $appId): void
    {
        $this->appId = $appId;
    }

    public function getApp(): ?AppEntity
    {
        return $this->app;
    }

    public function setApp(?AppEntity $app): void
    {
        $this->app = $app;
    }
}
