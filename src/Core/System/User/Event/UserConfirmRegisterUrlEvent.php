<?php declare(strict_types=1);

namespace Contena\Core\System\User\Event;

use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
class UserConfirmRegisterUrlEvent extends Event implements ContenaChannelEvent
{
    public function __construct(
        private readonly ChannelContext $channelContext,
        private string $confirmUrl,
        private readonly string $emailHash,
        private readonly ?string $userHash,
        private readonly UserEntity $user
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

    public function getUserHash(): ?string
    {
        return $this->userHash;
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }
}
