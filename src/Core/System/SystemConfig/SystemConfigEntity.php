<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Channel\ChannelEntity;

class SystemConfigEntity extends Entity
{
    use EntityIdTrait;

    protected string $configurationKey;

    /**
     * @var array<mixed>|bool|float|int|string|null
     */
    protected array|bool|float|int|string|null $configurationValue = null;

    protected ?string $tenantId = null;

    protected ?string $channelId = null;

    protected ?ChannelEntity $channel = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getConfigurationKey(): string
    {
        return $this->configurationKey;
    }

    public function setConfigurationKey(string $configurationKey): void
    {
        $this->configurationKey = $configurationKey;
    }

    /**
     * @return array<mixed>|bool|float|int|string|null
     */
    public function getConfigurationValue(): array|bool|float|int|string|null
    {
        return $this->configurationValue;
    }

    /**
     * @param array<mixed>|bool|float|int|string|null $configurationValue
     */
    public function setConfigurationValue(array|bool|float|int|string|null $configurationValue): void
    {
        $this->configurationValue = $configurationValue;
    }

    public function getChannelId(): ?string
    {
        return $this->channelId;
    }

    public function setChannelId(?string $channelId): void
    {
        $this->channelId = $channelId;
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
