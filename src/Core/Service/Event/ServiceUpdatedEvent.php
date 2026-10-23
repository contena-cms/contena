<?php declare(strict_types=1);

namespace Contena\Core\Service\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
readonly class ServiceUpdatedEvent implements ContenaEvent
{
    public function __construct(public string $service, private Context $context)
    {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
