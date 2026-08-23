<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Controller\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class PermissionDeniedException extends ContenaHttpException
{
    public function __construct()
    {
        parent::__construct('The user does not have the permission to do this action.');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PERMISSION_DENIED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
