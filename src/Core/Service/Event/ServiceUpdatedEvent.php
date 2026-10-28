<?php declare(strict_types=1);

namespace Contena\Core\Service\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
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
