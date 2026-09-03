<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Event;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Symfony\Contracts\EventDispatcher\Event;

final class PaymentGatewayCallCompletedEvent extends Event
{
    public function __construct(
        public readonly string $resourceType,
        public readonly string $resourceId,
        public readonly string $resourceNo,
        public readonly string $operation,
        public readonly string $channel,
        public readonly string $channelConfigId,
        public readonly PaymentResult $result,
        public readonly Context $context,
    ) {
    }
}
