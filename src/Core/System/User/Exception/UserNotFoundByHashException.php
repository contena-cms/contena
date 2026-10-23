<?php declare(strict_types=1);

namespace Contena\Core\System\User\Exception;

use Contena\Core\System\User\UserException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class UserNotFoundByHashException extends UserException
{
    public function __construct(string $hash)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::CUSTOMER_NOT_FOUND_BY_HASH,
            'No matching user for the hash "{{ hash }}" was found.',
            ['hash' => $hash]
        );
    }
}
