<?php declare(strict_types=1);

namespace Contena\Core\System\User\Event;

use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
class PasswordRecoveryUrlEvent extends Event implements ContenaChannelEvent
{
    public function __construct(
        private readonly ChannelContext $channelContext,
        private string $recoveryUrl,
        private readonly string $hash,
        private readonly string $frontendUrl,
        private readonly UserRecoveryEntity $userRecovery
    ) {
    }

    public function getRecoveryUrl(): string
    {
        return $this->recoveryUrl;
    }

    public function setRecoveryUrl(string $recoveryUrl): void
    {
        $this->recoveryUrl = $recoveryUrl;
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

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getFrontendUrl(): string
    {
        return $this->frontendUrl;
    }

    public function getUserRecovery(): UserRecoveryEntity
    {
        return $this->userRecovery;
    }
}
