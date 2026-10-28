<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class InvalidFileAccessTokenException extends ContenaHttpException
{
    public function __construct()
    {
        parent::__construct('Access to file denied due to invalid access token');
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_FILE_INVALID_ACCESS_TOKEN';
    }
}
