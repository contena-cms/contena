<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Unused exception will be removed
 *
 * @codeCoverageIgnore
 */
class MappingException extends ContenaHttpException
{
    public function __construct()
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__IMPORT_EXPORT_MAPPING_EXCEPTION';
    }
}
