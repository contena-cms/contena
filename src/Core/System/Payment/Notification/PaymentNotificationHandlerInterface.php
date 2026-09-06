<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Notification;

use Contena\Core\System\Payment\Notification\Struct\PaymentNotificationTarget;
use Contena\Core\System\Payment\Struct\GatewayResult;

/**
 * Adapts a verified gateway notification to its owning payment aggregate.
 */
interface PaymentNotificationHandlerInterface
{
    final public const string SERVICE_TAG = 'contena.payment.notification_handler';

    public function getType(): int;

    public function resolve(string $resourceNo, string $channelConfigId): PaymentNotificationTarget;

    public function apply(string $channel, string $channelConfigId, PaymentNotificationTarget $target, GatewayResult $result): void;
}
