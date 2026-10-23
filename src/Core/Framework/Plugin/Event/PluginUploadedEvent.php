<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Event;

use Contena\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class PluginUploadedEvent extends Event
{
    public function __construct(
        public readonly string $filename,
        public readonly Context $context,
        public readonly ?string $pluginName = null,
        public readonly ?string $pluginVersion = null
    ) {
    }
}
