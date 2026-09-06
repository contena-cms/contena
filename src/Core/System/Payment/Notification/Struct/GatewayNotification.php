<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Notification\Struct;

use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
final class GatewayNotification extends Struct
{
    /**
     * @param array<string, string|list<string>> $headers
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public readonly string $rawBody,
        public readonly array $headers = [],
        public readonly array $parameters = [],
    ) {
    }
}
