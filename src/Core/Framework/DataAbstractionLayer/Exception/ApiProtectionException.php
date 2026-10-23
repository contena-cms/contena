<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class ApiProtectionException extends ContenaHttpException
{
    public function __construct(string $accessor)
    {
        parent::__construct(
            'Accessor {{ accessor }} is not allowed in this api scope',
            ['accessor' => $accessor]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__ACCESSOR_NOT_ALLOWED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
