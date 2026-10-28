<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SalesChannelContextPermissionsChangedEvent extends NestedEvent implements ContenaSalesChannelEvent
{
    /**
     * @param array<string, bool> $permissions
     */
    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        protected array $permissions = []
    ) {
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    /**
     * @return array<string, bool>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }
}
