<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class InvalidMediaUrlException extends ContenaHttpException
{
    public function __construct(?string $url)
    {
        parent::__construct('Invalid media url: {{ url }}', ['url' => $url ?? 'null']);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_MEDIA_INVALID_URL';
    }
}
