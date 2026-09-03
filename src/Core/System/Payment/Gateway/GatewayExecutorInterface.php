<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

/**
 * @internal
 */
interface GatewayExecutorInterface
{
    /**
     * @param array<string, mixed> $config Provider-native configuration
     * @param array<string, mixed> $parameters
     */
    public function execute(string $provider, array $config, string $operation, array $parameters): mixed;
}
