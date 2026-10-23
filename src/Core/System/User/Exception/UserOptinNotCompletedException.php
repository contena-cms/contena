<?php declare(strict_types=1);

namespace Contena\Core\System\User\Exception;

use Contena\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class UserOptinNotCompletedException extends UserException
{
    public function __construct(
        string $id,
        int $statusCode = Response::HTTP_UNAUTHORIZED,
        string $errorCode = self::CUSTOMER_OPTIN_NOT_COMPLETED,
    ) {
        parent::__construct(
            $statusCode,
            $errorCode,
            'The user with the id "{{ userId }}" has not completed the opt-in.',
            ['userId' => $id]
        );
    }

    public function getSnippetKey(): string
    {
        return 'account.doubleOptinAccountAlert';
    }
}
