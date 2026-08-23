<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\MemberEntity;
use Symfony\Contracts\EventDispatcher\Event;

class MemberConfirmRegisterUrlEvent extends Event implements ContenaChannelEvent
{
    public function __construct(
        private readonly ChannelContext $channelContext,
        private string $confirmUrl,
        private readonly string $emailHash,
        private readonly ?string $memberHash,
        private readonly MemberEntity $member,
    ) {
    }

    public function getConfirmUrl(): string
    {
        return $this->confirmUrl;
    }

    public function setConfirmUrl(string $confirmUrl): void
    {
        $this->confirmUrl = $confirmUrl;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getChannelId(): string
    {
        return $this->channelContext->getChannelId();
    }

    public function getEmailHash(): string
    {
        return $this->emailHash;
    }

    public function getMemberHash(): ?string
    {
        return $this->memberHash;
    }

    public function getMember(): MemberEntity
    {
        return $this->member;
    }
}
