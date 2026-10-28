<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
class SalesChannelContextCreatedEvent extends Event implements ContenaSalesChannelEvent
{
    /**
     * @param array<string, mixed> $session
     */
    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private readonly string $usedToken,
        private readonly array $session = []
    ) {
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getUsedToken(): string
    {
        return $this->usedToken;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSession(): array
    {
        return $this->session;
    }
}
