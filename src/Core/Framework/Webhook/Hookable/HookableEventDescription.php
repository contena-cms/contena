<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Hookable;

use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
readonly class HookableEventDescription
{
    /**
     * @param list<string> $privileges
     */
    public function __construct(
        public string $eventName,
        public string $description,
        public array $privileges,
    ) {
    }
}
