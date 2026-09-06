<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\System\Payment\Notification\Struct\GatewayNotificationResult;
use Contena\Core\System\Payment\Notification\Struct\PaymentNotificationTarget;
use Contena\Tests\Integration\Core\System\Payment\PaymentServiceTest;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 *
 * @see PaymentServiceTest
 */
final class GatewayNotificationProcessedEvent extends Event implements ContenaEvent
{
    public function __construct(
        public readonly string $recordId,
        public readonly GatewayNotificationResult $notification,
        public readonly PaymentNotificationTarget $target,
    ) {
    }

    public function getContext(): Context
    {
        return $this->target->context;
    }
}
