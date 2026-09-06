<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Symfony\Contracts\EventDispatcher\Event;

final class PaymentGatewayCompletedEvent extends Event implements ContenaEvent
{
    public function __construct(
        public readonly string $operation,
        public readonly PaymentEntityReference $entity,
        public readonly string $channel,
        public readonly string $channelConfigId,
        private readonly Context $context,
        public readonly PaymentResult $result,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
