<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\File\Event;

use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class SalesChannelFileTemplateResolveEvent extends Event
{
    public function __construct(public readonly string $salesChannelId)
    {
    }
}
