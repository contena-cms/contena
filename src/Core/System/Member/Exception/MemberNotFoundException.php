<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Exception;

use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class MemberNotFoundException extends MemberException
{
    public function __construct(string $email)
    {
        parent::__construct(
            Response::HTTP_UNAUTHORIZED,
            self::MEMBER_NOT_FOUND,
            'No matching member for the email "{{ email }}" was found.',
            ['email' => $email],
        );
    }
}
