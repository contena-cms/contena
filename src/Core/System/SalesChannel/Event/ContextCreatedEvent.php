<?php

declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;

/**
 * This event can be used to react to the creation of a new context.
 * It must be used very carefully, as it practically effects every part of Contena.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class ContextCreatedEvent
{
    public function __construct(
        public Context $context,
    ) {
    }
}
