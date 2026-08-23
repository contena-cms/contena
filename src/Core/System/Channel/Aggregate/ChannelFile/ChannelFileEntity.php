<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelFile;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Channel\ChannelEntity;

class ChannelFileEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $channelId;

    protected ?ChannelEntity $channel = null;

    protected string $fileFamily;

    protected string $fileName;

    protected bool $enabled = false;

    /**
     * @var array<string, string>
     */
    protected array $templateOverrides = [];

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

    public function getChannel(): ?ChannelEntity
    {
        return $this->channel;
    }

    public function setChannel(?ChannelEntity $channel): void
    {
        $this->channel = $channel;
    }

    public function getFileFamily(): string
    {
        return $this->fileFamily;
    }

    public function setFileFamily(string $fileFamily): void
    {
        $this->fileFamily = $fileFamily;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * @return array<string, string>
     */
    public function getTemplateOverrides(): array
    {
        return $this->templateOverrides;
    }

    /**
     * @param array<string, string> $templateOverrides
     */
    public function setTemplateOverrides(array $templateOverrides): void
    {
        $this->templateOverrides = $templateOverrides;
    }
}
