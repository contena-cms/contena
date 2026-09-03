<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\PaymentException;
use Yansongda\Pay\Pay;

/**
 * @internal
 */
final class GatewayExecutor implements GatewayExecutorInterface
{
    public function execute(string $provider, array $config, string $operation, array $parameters): mixed
    {
        Pay::clear();
        Pay::config([$provider => ['default' => $config]]);

        try {
            $gateway = Pay::get($provider);
            $callback = [$gateway, $operation];
            if (!\is_callable($callback)) {
                throw PaymentException::capabilityNotSupported($provider, $operation);
            }

            return $callback($parameters);
        } finally {
            Pay::clear();
        }
    }
}
