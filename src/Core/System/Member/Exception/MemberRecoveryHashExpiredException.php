<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Exception;

use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class MemberRecoveryHashExpiredException extends MemberException
{
    public function __construct(string $hash)
    {
        parent::__construct(
            Response::HTTP_GONE,
            self::MEMBER_RECOVERY_HASH_EXPIRED,
            'The hash "{{ hash }}" is expired.',
            ['hash' => $hash]
        );
    }
}
