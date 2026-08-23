<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Exception;

use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class InvalidImitateMemberTokenException extends MemberException
{
    public function __construct(string $token)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::IMITATE_MEMBER_INVALID_TOKEN,
            'The token "{{ token }}" is invalid.',
            ['token' => $token]
        );
    }
}
