<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogComment;

use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Member\MemberEntity;

/**
 * @codeCoverageIgnore
 */
class BlogCommentEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $dataScopeId;

    protected string $blogId;

    protected ?string $memberId = null;

    protected string $channelId;

    protected string $languageId;

    protected ?string $parentId = null;

    protected ?string $externalUser = null;

    protected ?string $externalEmail = null;

    protected ?string $content = null;

    protected bool $status = false;

    protected ?BlogEntity $blog = null;

    protected ?MemberEntity $member = null;

    protected ?ChannelEntity $channel = null;

    protected ?LanguageEntity $language = null;

    protected ?BlogCommentEntity $parent = null;

    protected ?BlogCommentCollection $children = null;

    public function getDataScopeId(): string
    {
        return $this->dataScopeId;
    }

    public function setDataScopeId(string $dataScopeId): void
    {
        $this->dataScopeId = $dataScopeId;
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    public function setBlogId(string $blogId): void
    {
        $this->blogId = $blogId;
    }

    public function getMemberId(): ?string
    {
        return $this->memberId;
    }

    public function setMemberId(?string $memberId): void
    {
        $this->memberId = $memberId;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function setChannelId(string $channelId): void
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

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getExternalUser(): ?string
    {
        return $this->externalUser;
    }

    public function setExternalUser(?string $externalUser): void
    {
        $this->externalUser = $externalUser;
    }

    public function getExternalEmail(): ?string
    {
        return $this->externalEmail;
    }

    public function setExternalEmail(?string $externalEmail): void
    {
        $this->externalEmail = $externalEmail;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getStatus(): bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): void
    {
        $this->status = $status;
    }

    public function getBlog(): ?BlogEntity
    {
        return $this->blog;
    }

    public function setBlog(?BlogEntity $blog): void
    {
        $this->blog = $blog;
    }

    public function getMember(): ?MemberEntity
    {
        return $this->member;
    }

    public function setMember(?MemberEntity $member): void
    {
        $this->member = $member;
    }

    public function getChannel(): ?ChannelEntity
    {
        return $this->channel;
    }

    public function setChannel(?ChannelEntity $channel): void
    {
        $this->channel = $channel;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(?LanguageEntity $language): void
    {
        $this->language = $language;
    }

    public function getParent(): ?BlogCommentEntity
    {
        return $this->parent;
    }

    public function setParent(?BlogCommentEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?BlogCommentCollection
    {
        return $this->children;
    }

    public function setChildren(BlogCommentCollection $children): void
    {
        $this->children = $children;
    }
}
