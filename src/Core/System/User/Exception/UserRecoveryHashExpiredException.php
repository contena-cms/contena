<?php declare(strict_types=1);

namespace Contena\Core\System\User\Exception;

use Contena\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class UserRecoveryHashExpiredException extends UserException
{
    public function __construct(string $hash)
    {
        parent::__construct(
            Response::HTTP_GONE,
            self::CUSTOMER_RECOVERY_HASH_EXPIRED,
            'The hash "{{ hash }}" is expired.',
            ['hash' => $hash]
        );
    }
}
