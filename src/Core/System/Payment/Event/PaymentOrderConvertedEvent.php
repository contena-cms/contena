<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\Routing\PaymentRoute;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Modify the converted order data before identity allocation and persistence.
 * Throwing rejects conversion before any order or provider call is created.
 */
final class PaymentOrderConvertedEvent extends Event implements ContenaEvent
{
    /**
     * @param array<string, mixed> $convertedOrder
     */
    public function __construct(
        public readonly PaymentAppEntity $app,
        public readonly PaymentRequest $request,
        public readonly PaymentRoute $route,
        public array $convertedOrder,
        private readonly Context $context,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
