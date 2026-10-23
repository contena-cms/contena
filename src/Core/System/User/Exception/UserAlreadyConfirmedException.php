<?php declare(strict_types=1);

namespace Contena\Core\System\User\Exception;

use Contena\Core\System\User\UserException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class UserAlreadyConfirmedException extends UserException
{
    public function __construct(string $id)
    {
        parent::__construct(
            Response::HTTP_PRECONDITION_FAILED,
            self::CUSTOMER_IS_ALREADY_CONFIRMED,
            'The user with the id "{{ userId }}" is already confirmed.',
            ['userId' => $id]
        );
    }
}
