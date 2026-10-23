<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File\Event;

use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class ChannelFileTemplateResolveEvent extends Event
{
    public function __construct(public readonly string $channelId)
    {
    }
}
