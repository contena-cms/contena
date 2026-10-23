<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File\Api;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class ChannelFileAdministrationConfiguration
{
    /**
     * @param array<string, string> $templateOverrides
     */
    public function __construct(
        public string $id,
        public bool $enabled,
        public array $templateOverrides,
    ) {
    }
}
