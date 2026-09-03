<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Struct\SubscriptionRequest;

interface SubscribeHandlerInterface extends GatewayInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function subscribe(SubscriptionRequest $request, array $config): PaymentResult;
}
