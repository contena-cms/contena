<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Context;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
interface SalesChannelContextServiceInterface
{
    public function get(SalesChannelContextServiceParameters $parameters): SalesChannelContext;
}
