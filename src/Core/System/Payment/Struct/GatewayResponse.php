<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;

/**
 * The normalized provider response and the untouched provider payload.
 *
 * @codeCoverageIgnore
 */
final class GatewayResponse extends Struct
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly ?string $requestId = null,
        public readonly ?string $resourceId = null,
        public readonly ?string $code = null,
        public readonly ?string $message = null,
        public readonly array $data = [],
    ) {
    }
}
