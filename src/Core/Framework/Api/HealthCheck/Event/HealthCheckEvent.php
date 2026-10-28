<?php

declare(strict_types=1);

namespace Contena\Core\Framework\Api\HealthCheck\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class HealthCheckEvent extends Event
{
    public function __construct(
        public readonly Context $context
    ) {
    }
}
