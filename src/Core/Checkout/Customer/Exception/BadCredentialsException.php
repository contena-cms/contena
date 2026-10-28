<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Exception;

use Contena\Core\Checkout\Customer\CustomerException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class BadCredentialsException extends CustomerException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_UNAUTHORIZED,
            self::CUSTOMER_AUTH_BAD_CREDENTIALS,
            'Invalid username and/or password.'
        );
    }
}
