<?php declare(strict_types=1);

namespace Contena\Core\Framework\Increment;

/**
 * @final
 */
class IncrementGatewayRegistry
{
    final public const string USER_ACTIVITY_POOL = 'user_activity';

    /**
     * @param AbstractIncrementer[] $gateways
     */
    public function __construct(private readonly iterable $gateways)
    {
    }

    public function get(string $pool): AbstractIncrementer
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->getPool() === $pool) {
                return $gateway;
            }
        }

        throw IncrementException::gatewayNotFound($pool);
    }
}
