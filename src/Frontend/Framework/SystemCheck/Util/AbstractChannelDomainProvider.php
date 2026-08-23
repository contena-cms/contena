<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\SystemCheck\Util;

/**
 * @internal
 */
abstract class AbstractChannelDomainProvider
{
    abstract public function fetchChannelDomains(): ChannelDomainCollection;
}
