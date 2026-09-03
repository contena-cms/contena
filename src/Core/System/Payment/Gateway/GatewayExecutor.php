<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\PaymentException;
use DI\Container;
use Yansongda\Artful\Contract\ShortcutInterface;
use Yansongda\Pay\Pay;
use Yansongda\Supports\Str;

/**
 * @internal
 */
final class GatewayExecutor implements GatewayExecutorInterface
{
    public function execute(string $provider, array $config, string $operation, array $parameters): mixed
    {
        Pay::clear();
        Pay::config([$provider => ['default' => $config]], new Container());

        try {
            $gateway = Pay::get($provider);
            $callback = [$gateway, $operation];
            if (!\is_object($gateway) || !$this->supports($gateway, $provider, $operation) || !\is_callable($callback)) {
                throw PaymentException::capabilityNotSupported($provider, $operation);
            }

            return $callback($parameters);
        } finally {
            Pay::clear();
        }
    }

    private function supports(object $gateway, string $provider, string $operation): bool
    {
        if (method_exists($gateway, $operation)) {
            return true;
        }

        $shortcut = \sprintf(
            'Yansongda\\Pay\\Shortcut\\%s\\%sShortcut',
            Str::studly($provider),
            Str::studly($operation),
        );

        return is_subclass_of($shortcut, ShortcutInterface::class);
    }
}
