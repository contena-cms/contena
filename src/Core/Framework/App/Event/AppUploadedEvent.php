<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Event;

use Contena\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class AppUploadedEvent extends Event
{
    public function __construct(
        public readonly string $filename,
        public readonly Context $context,
        public readonly ?string $appName = null,
        public readonly ?string $appVersion = null
    ) {
    }
}
