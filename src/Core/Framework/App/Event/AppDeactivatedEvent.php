<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Event;

use Contena\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class AppDeactivatedEvent extends AppChangedEvent
{
    final public const NAME = 'app.deactivated';

    public function getName(): string
    {
        return self::NAME;
    }
}
