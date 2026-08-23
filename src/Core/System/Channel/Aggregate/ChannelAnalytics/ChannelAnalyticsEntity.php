<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelAnalytics;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Channel\ChannelEntity;

class ChannelAnalyticsEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $trackingId;

    protected bool $active;

    protected bool $anonymizeIp;

    protected ?ChannelEntity $channel = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getTrackingId(): string
    {
        return $this->trackingId;
    }

    public function setTrackingId(string $trackingId): void
    {
        $this->trackingId = $trackingId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function isAnonymizeIp(): bool
    {
        return $this->anonymizeIp;
    }

    public function setAnonymizeIp(bool $anonymizeIp): void
    {
        $this->anonymizeIp = $anonymizeIp;
    }

    public function getChannel(): ?ChannelEntity
    {
        return $this->channel;
    }

    public function setChannel(ChannelEntity $channel): void
    {
        $this->channel = $channel;
    }
}
