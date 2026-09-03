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
            $this->gateways[$gateway->code()] = $gateway;
        }
    }

    public function get(string $code): GatewayInterface
    {
        return $this->gateways[$code] ?? throw PaymentException::gatewayNotFound($code);
    }

    public function supports(string $code, string $operation): bool
    {
        $gateway = $this->gateways[$code] ?? null;
        if (!$gateway instanceof GatewayInterface) {
            return false;
        }

        return match ($operation) {
            PaymentOperation::PAY => $gateway instanceof PaymentHandlerInterface,
            PaymentOperation::QUERY => $gateway instanceof QueryHandlerInterface,
            PaymentOperation::REFUND => $gateway instanceof RefundHandlerInterface,
            PaymentOperation::TRANSFER => $gateway instanceof TransferHandlerInterface,
            PaymentOperation::SUBSCRIBE => $gateway instanceof SubscribeHandlerInterface,
            default => false,
        };
    }
}
