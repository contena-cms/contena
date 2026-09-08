<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Event;

/**
 * @final
 */
class AppInstalledEvent extends ManifestChangedEvent
{
    final public const NAME = 'app.installed';

    public function getName(): string
    {
        return self::NAME;
    }
}
