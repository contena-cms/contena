<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class ContenaAccountLoginEvent implements ContenaEvent
{
    public function __construct(
        private readonly Context $context,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
