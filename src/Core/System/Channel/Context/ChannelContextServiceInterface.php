<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
interface ChannelContextServiceInterface
{
    public function get(ChannelContextServiceParameters $parameters): ChannelContext;
}
