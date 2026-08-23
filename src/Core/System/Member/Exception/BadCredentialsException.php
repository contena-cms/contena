<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Exception;

use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class BadCredentialsException extends MemberException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_UNAUTHORIZED,
            self::MEMBER_AUTH_BAD_CREDENTIALS,
            'Invalid username and/or password.',
        );
    }
}
