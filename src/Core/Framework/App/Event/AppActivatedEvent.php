<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Event;

use Contena\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class AppActivatedEvent extends AppChangedEvent
{
    final public const NAME = 'app.activated';

    public function getName(): string
    {
        return self::NAME;
    }
}
