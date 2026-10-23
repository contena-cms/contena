<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelAnalytics;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\System\Channel\ChannelEntity;

class ChannelAnalyticsEntity extends Entity
{
    use EntityIdTrait;

    protected string $trackingId;

    protected bool $active;

    protected bool $trackOrders;

    protected bool $anonymizeIp;

    protected bool $trackOffcanvasCart = false;

    protected bool $enhancedConversions = false;

    protected ?ChannelEntity $channel = null;

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

    public function isTrackOrders(): bool
    {
        return $this->trackOrders;
    }

    public function setTrackOrders(bool $trackOrders): void
    {
        $this->trackOrders = $trackOrders;
    }

    public function isAnonymizeIp(): bool
    {
        return $this->anonymizeIp;
    }

    public function setAnonymizeIp(bool $anonymizeIp): void
    {
        $this->anonymizeIp = $anonymizeIp;
    }

    public function isTrackOffcanvasCart(): bool
    {
        return $this->trackOffcanvasCart;
    }

    public function setTrackOffcanvasCart(bool $trackOffcanvasCart): void
    {
        $this->trackOffcanvasCart = $trackOffcanvasCart;
    }

    public function isEnhancedConversions(): bool
    {
        return $this->enhancedConversions;
    }

    public function setEnhancedConversions(bool $enhancedConversions): void
    {
        $this->enhancedConversions = $enhancedConversions;
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
