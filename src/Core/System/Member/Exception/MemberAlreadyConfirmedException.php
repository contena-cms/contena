<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Exception;

use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class MemberAlreadyConfirmedException extends MemberException
{
    public function __construct(string $id)
    {
        parent::__construct(
            Response::HTTP_PRECONDITION_FAILED,
            self::MEMBER_ALREADY_CONFIRMED,
            'The member with the id "{{ memberId }}" is already confirmed.',
            ['memberId' => $id]
        );
    }
}
