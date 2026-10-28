<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Exception;

use Contena\Core\Checkout\Customer\CustomerException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerRecoveryHashExpiredException extends CustomerException
{
    public function __construct(string $hash)
    {
        parent::__construct(
            Response::HTTP_GONE,
            self::CUSTOMER_RECOVERY_HASH_EXPIRED,
            'The hash "{{ hash }}" is expired.',
            ['hash' => $hash]
        );
    }
}
