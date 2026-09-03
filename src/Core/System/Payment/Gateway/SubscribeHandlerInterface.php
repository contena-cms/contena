<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringEntity;
use Contena\Core\System\Payment\Struct\PaymentResult;

interface SubscribeHandlerInterface extends GatewayInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function subscribe(PaymentRecurringEntity $subscription, array $config): PaymentResult;
}
