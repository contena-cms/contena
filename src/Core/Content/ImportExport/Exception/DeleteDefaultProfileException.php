<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class DeleteDefaultProfileException extends ContenaHttpException
{
    /**
     * @param list<string> $ids
     */
    public function __construct(array $ids)
    {
        parent::__construct('Cannot delete system default import_export_profile', ['ids' => $ids]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_DELETE_DEFAULT_PROFILE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
