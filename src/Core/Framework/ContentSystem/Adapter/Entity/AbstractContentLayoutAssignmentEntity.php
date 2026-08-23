<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Adapter\Entity;

use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * Shared properties for sales channel and content layout across content layout assignments.
 */
abstract class AbstractContentLayoutAssignmentEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $channelId = null;

    protected string $contentLayoutId;

    protected ?ChannelEntity $channel = null;

    protected ?ContentLayoutEntity $contentLayout = null;

    public function getChannelId(): ?string
    {
        return $this->channelId;
    }

    public function setChannelId(?string $channelId): void
    {
        $this->channelId = $channelId;
    }

    public function getContentLayoutId(): string
    {
        return $this->contentLayoutId;
    }

    public function setContentLayoutId(string $contentLayoutId): void
    {
        $this->contentLayoutId = $contentLayoutId;
    }

    public function getChannel(): ?ChannelEntity
    {
        return $this->channel;
    }

    public function setChannel(?ChannelEntity $channel): void
    {
        $this->channel = $channel;
    }

    public function getContentLayout(): ?ContentLayoutEntity
    {
        return $this->contentLayout;
    }

    public function setContentLayout(?ContentLayoutEntity $contentLayout): void
    {
        $this->contentLayout = $contentLayout;
    }
}
