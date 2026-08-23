<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Exception;

use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class MemberOptinNotCompletedException extends MemberException
{
    public function __construct(string $id)
    {
        parent::__construct(
            Response::HTTP_UNAUTHORIZED,
            self::MEMBER_OPTIN_NOT_COMPLETED,
            'The member with the id "{{ memberId }}" has not completed the opt-in.',
            ['memberId' => $id],
        );
    }

    public function getSnippetKey(): string
    {
        return 'account.doubleOptinAccountAlert';
    }
}
