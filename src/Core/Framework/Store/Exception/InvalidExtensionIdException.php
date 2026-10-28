<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class InvalidExtensionIdException extends ContenaHttpException
{
    public function __construct(
        array $parameters = [],
        ?\Throwable $e = null
    ) {
        parent::__construct('The extension id must be an non empty numeric value.', $parameters, $e);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_EXTENSION_ID';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
