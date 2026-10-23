<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\SystemCheck\Util;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
abstract class AbstractChannelDomainProvider
{
    abstract public function fetchChannelDomains(): ChannelDomainCollection;
}
