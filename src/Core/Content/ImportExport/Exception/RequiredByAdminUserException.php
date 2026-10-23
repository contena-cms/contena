<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class RequiredByAdminUserException extends ContenaHttpException
{
    public function __construct(string $column)
    {
        parent::__construct('{{ column }} is set to required by the admin_user but has no value', [
            'column' => $column,
        ]);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_REQUIRED_BY_USER';
    }
}
