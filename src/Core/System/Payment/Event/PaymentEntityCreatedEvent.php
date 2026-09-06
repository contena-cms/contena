<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\System\Payment\Struct\PaymentEntityReference;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched inside the local write transaction. Durable subscribers should
 * enqueue their work in that transaction and deliver it asynchronously.
 *
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
final class PaymentEntityCreatedEvent extends Event implements ContenaEvent
{
    public function __construct(
        public readonly PaymentEntityReference $entity,
        private readonly Context $context,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
