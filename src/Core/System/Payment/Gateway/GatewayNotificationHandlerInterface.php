<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\Struct\GatewayNotification;
use Contena\Core\System\Payment\Struct\GatewayNotificationResult;

interface GatewayNotificationHandlerInterface extends GatewayInterface
{
    /**
     * Verifies and maps an incoming notification from the payment channel.
     *
     * @param array<string, mixed> $config
     */
    public function handleNotification(GatewayNotification $notification, array $config): GatewayNotificationResult;
}
