<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

/**
 * @internal
 */
interface YansongdaPayClientInterface
{
    /**
     * @param array<string, mixed> $config Provider-native configuration
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed> Normalized SDK response, without interpreting financial status
     */
    public function request(string $provider, array $config, string $operation, array $parameters): array;
}
