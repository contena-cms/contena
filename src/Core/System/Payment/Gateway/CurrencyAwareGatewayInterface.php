<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

/**
 * Implement this contract when a gateway supports only an explicit set of settlement currencies.
 * Gateways that do not implement it remain currency-agnostic for backward compatibility.
 */
interface CurrencyAwareGatewayInterface extends GatewayInterface
{
    public function supportsCurrency(string $currencyCode): bool;
}
