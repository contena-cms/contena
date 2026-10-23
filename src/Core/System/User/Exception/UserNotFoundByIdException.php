<?php declare(strict_types=1);

namespace Contena\Core\System\User\Exception;

use Contena\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class UserNotFoundByIdException extends UserException
{
    public function __construct(string $id)
    {
        parent::__construct(
            Response::HTTP_UNAUTHORIZED,
            self::CUSTOMER_NOT_FOUND_BY_ID,
            'No matching user for the id "{{ id }}" was found.',
            ['id' => $id]
        );
    }
}
