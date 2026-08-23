<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Exception;

use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class MemberNotFoundByHashException extends MemberException
{
    public function __construct(string $hash)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::MEMBER_NOT_FOUND_BY_HASH,
            'No matching member for the hash "{{ hash }}" was found.',
            ['hash' => $hash]
        );
    }
}
