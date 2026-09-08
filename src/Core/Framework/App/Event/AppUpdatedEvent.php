<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Event;

/**
 * @final
 */
class AppUpdatedEvent extends ManifestChangedEvent
{
    final public const NAME = 'app.updated';

    public function getName(): string
    {
        return self::NAME;
    }
}
