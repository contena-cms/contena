<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Exception;

use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class MemberNotFoundByIdException extends MemberException
{
    public function __construct(string $id)
    {
        parent::__construct(
            Response::HTTP_UNAUTHORIZED,
            self::MEMBER_NOT_FOUND_BY_ID,
            'No matching member for the id "{{ id }}" was found.',
            ['id' => $id],
        );
    }
}
