<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Store\Struct\ExtensionCollection;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class InstalledExtensionsListingLoadedEvent extends Event
{
    public function __construct(public ExtensionCollection $extensionCollection, public readonly Context $context)
    {
    }
}
