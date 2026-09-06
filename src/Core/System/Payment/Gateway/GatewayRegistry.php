<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\PaymentException;

final class GatewayRegistry
{
    /**
     * @var array<string, GatewayInterface>
     */
    private array $gateways = [];

    /**
     * @internal
     *
     * @param iterable<GatewayInterface> $gateways
     */
    public function __construct(iterable $gateways)
    {
        foreach ($gateways as $gateway) {
            if ($gateway->code() === '' || isset($this->gateways[$gateway->code()])) {
                throw PaymentException::invalidExtensionRegistration(GatewayInterface::class, $gateway->code());
            }
            $this->gateways[$gateway->code()] = $gateway;
        }
    }

    public function get(string $code): GatewayInterface
    {
        return $this->gateways[$code] ?? throw PaymentException::gatewayNotFound($code);
    }

    public function has(string $code): bool
    {
        return isset($this->gateways[$code]);
    }
}
