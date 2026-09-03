<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;

final class GatewayNotificationResponse extends Struct
{
    public function __construct(
        public readonly string $body,
        public readonly string $contentType = 'text/plain',
        public readonly int $status = 200,
    ) {
    }
}
