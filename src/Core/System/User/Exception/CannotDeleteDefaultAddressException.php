<?php declare(strict_types=1);

namespace Contena\Core\System\User\Exception;

use Contena\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class CannotDeleteDefaultAddressException extends UserException
{
    public function __construct(string $id)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_ADDRESS_IS_DEFAULT,
            'User address with id "{{ addressId }}" is a default address and cannot be deleted.',
            ['addressId' => $id]
        );
    }
}
