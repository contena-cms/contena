<?php

declare(strict_types=1);

namespace Contena\Core\System\Channel\Event;

use Contena\Core\Framework\Context;

/**
 * This event can be used to react to the creation of a new context.
 * It must be used very carefully, as it practically effects every part of Contena.
 */
final class ContextCreatedEvent
{
    public function __construct(
        public Context $context,
    ) {
    }
}
