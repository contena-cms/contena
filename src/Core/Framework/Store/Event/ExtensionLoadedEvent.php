<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\PluginEntity;
use Contena\Core\Framework\Store\Struct\ExtensionStruct;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fired by ExtensionLoader after building the ExtensionStruct for an installed plugin.
 * Listeners receive the source entity and the resulting struct and may enrich it - e.g. Frontend
 * checks the source and flags themes via $extension->setIsTheme(true) - without Core depending on Frontend.
 *
 * @internal
 */
final class ExtensionLoadedEvent extends Event
{
    public function __construct(
        public readonly PluginEntity $source,
        public readonly ExtensionStruct $extension,
        public readonly Context $context,
    ) {
    }
}
