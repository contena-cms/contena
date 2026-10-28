<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ShopId;

use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ShopIdDeletedEvent extends Event
{
}
