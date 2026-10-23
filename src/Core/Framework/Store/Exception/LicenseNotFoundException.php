<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

class LicenseNotFoundException extends ContenaHttpException
{
    public function __construct(
        int $licenseId,
        array $parameters = [],
        ?\Throwable $e = null
    ) {
        $parameters['licenseId'] = $licenseId;

        parent::__construct('Could not find license with id {{licenseId}}', $parameters, $e);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__LICENSE_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
