<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserWishlist;

use Contena\Core\System\User\Aggregate\UserWishlistBlog\UserWishlistBlogCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @codeCoverageIgnore
 */
class UserWishlistEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $userId;

    protected string $channelId;

    protected ?UserEntity $user = null;

    protected ?ChannelEntity $channel = null;

    protected ?UserWishlistBlogCollection $blogs = null;

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getBlogs(): ?UserWishlistBlogCollection
    {
        return $this->blogs;
    }

    public function setBlogs(UserWishlistBlogCollection $blogs): void
    {
        $this->blogs = $blogs;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function setChannelId(string $channelId): void
    {
        $this->channelId = $channelId;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(UserEntity $user): void
    {
        $this->user = $user;
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
