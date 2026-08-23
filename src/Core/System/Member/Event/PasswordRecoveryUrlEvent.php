<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryEntity;
use Symfony\Contracts\EventDispatcher\Event;

class PasswordRecoveryUrlEvent extends Event implements ContenaChannelEvent
{
    public function __construct(
        private readonly ChannelContext $channelContext,
        private string $recoveryUrl,
        private readonly string $hash,
        private readonly string $frontendUrl,
        private readonly MemberRecoveryEntity $memberRecovery
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

    public function getMemberRecovery(): MemberRecoveryEntity
    {
        return $this->memberRecovery;
    }
}
