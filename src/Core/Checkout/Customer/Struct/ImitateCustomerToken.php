<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Struct;

use Contena\Core\Framework\JWT\Struct\JWTStruct;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class ImitateCustomerToken extends JWTStruct
{
    public string $salesChannelId;

    public string $customerId;
}
