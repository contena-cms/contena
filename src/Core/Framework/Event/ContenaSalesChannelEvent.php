<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
interface ContenaSalesChannelEvent extends ContenaEvent
{
    public function getSalesChannelContext(): SalesChannelContext;
}
