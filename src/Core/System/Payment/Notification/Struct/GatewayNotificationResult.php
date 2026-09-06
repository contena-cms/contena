<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Notification\Struct;

use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\Payment\Struct\GatewayResult;

/**
 * @codeCoverageIgnore
 */
final class GatewayNotificationResult extends Struct
{
    public function __construct(
        public readonly int $type,
        public readonly string $resourceNo,
        public readonly GatewayResult $result,
        public readonly string $responseBody,
        public readonly string $responseContentType = 'text/plain',
        public readonly int $responseStatus = 200,
    ) {
    }
}
