<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Exception;

use Contena\Core\Checkout\Customer\CustomerException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CannotDeleteDefaultAddressException extends CustomerException
{
    public function __construct(string $id)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_ADDRESS_IS_DEFAULT,
            'Customer address with id "{{ addressId }}" is a default address and cannot be deleted.',
            ['addressId' => $id]
        );
    }
}
