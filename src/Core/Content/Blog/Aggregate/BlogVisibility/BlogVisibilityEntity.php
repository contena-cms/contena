<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogVisibility;

use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Channel\ChannelEntity;

class BlogVisibilityEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected int $visibility;

    protected string $blogId;

    protected string $channelId;

    protected ?BlogEntity $blog = null;

    protected ?ChannelEntity $channel = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getVisibility(): int
    {
        return $this->visibility;
    }

    public function setVisibility(int $visibility): void
    {
        $this->visibility = $visibility;
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    public function setBlogId(string $blogId): void
    {
        $this->blogId = $blogId;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function setChannelId(string $channelId): void
    {
        $this->channelId = $channelId;
    }

    public function getBlog(): ?BlogEntity
    {
        return $this->blog;
    }

    public function setBlog(BlogEntity $blog): void
    {
        $this->blog = $blog;
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
