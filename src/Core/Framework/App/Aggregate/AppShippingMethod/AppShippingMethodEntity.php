<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppShippingMethod;

use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AppShippingMethodEntity extends Entity
{
    use EntityIdTrait;

    protected ?AppEntity $app = null;

    protected ?string $appId = null;

    protected string $identifier;

    protected string $appName;

    protected string $shippingMethodId;


    protected ?string $originalMediaId = null;

    protected ?MediaEntity $originalMedia = null;

    public function getApp(): ?AppEntity
    {
        return $this->app;
    }

    public function setApp(?AppEntity $app): void
    {
        $this->app = $app;
    }

    public function getAppId(): ?string
    {
        return $this->appId;
    }

    public function setAppId(?string $appId): void
    {
        $this->appId = $appId;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getAppName(): string
    {
        return $this->appName;
    }

    public function setAppName(string $appName): void
    {
        $this->appName = $appName;
    }

    public function getShippingMethodId(): string
    {
        return $this->shippingMethodId;
    }

    public function setShippingMethodId(string $shippingMethodId): void
    {
        $this->shippingMethodId = $shippingMethodId;
    }



    public function getOriginalMediaId(): ?string
    {
        return $this->originalMediaId;
    }

    public function setOriginalMediaId(?string $originalMediaId): void
    {
        $this->originalMediaId = $originalMediaId;
    }

    public function getOriginalMedia(): ?MediaEntity
    {
        return $this->originalMedia;
    }

    public function setOriginalMedia(?MediaEntity $originalMedia): void
    {
        $this->originalMedia = $originalMedia;
    }
}
