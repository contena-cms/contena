<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Exception;

use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class AddressNotFoundException extends MemberException
{
    public function __construct(string $id)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::MEMBER_ADDRESS_NOT_FOUND,
            'Member address with id "{{ addressId }}" not found.',
            ['addressId' => $id]
        );
    }
}
