<?php declare(strict_types=1);

namespace Contena\Core\System\User\Exception;

use Contena\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class BadCredentialsException extends UserException
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
