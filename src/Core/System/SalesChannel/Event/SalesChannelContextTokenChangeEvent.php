<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SalesChannelContextTokenChangeEvent extends Event implements ContenaSalesChannelEvent
{
    public function __construct(
        protected SalesChannelContext $salesChannelContext,
        protected string $previousToken,
        protected string $currentToken
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

    public function getPreviousToken(): string
    {
        return $this->previousToken;
    }

    public function getCurrentToken(): string
    {
        return $this->currentToken;
    }
}
