<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\BaseChannelContext;

/**
 * Loads user-independent information for a sales channel, which could be cached separately.
 *
 * @internal
 */
abstract class AbstractBaseChannelContextFactory
{
    /**
     * @param array<string, mixed> $options
     */
    abstract public function create(string $channelId, array $options = []): BaseChannelContext;
}
