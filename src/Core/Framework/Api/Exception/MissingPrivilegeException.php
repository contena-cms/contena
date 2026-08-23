<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class MissingPrivilegeException extends ContenaHttpException
{
    final public const string MISSING_PRIVILEGE_ERROR = 'FRAMEWORK__MISSING_PRIVILEGE_ERROR';

    /**
     * @param list<string> $privilege
     */
    public function __construct(array $privilege = [])
    {
        $errorMessage = json_encode([
            'message' => 'Missing privilege',
            'missingPrivileges' => $privilege,
        ], \JSON_THROW_ON_ERROR);

        parent::__construct($errorMessage ?: '');
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    public function getErrorCode(): string
    {
        return self::MISSING_PRIVILEGE_ERROR;
    }
}
