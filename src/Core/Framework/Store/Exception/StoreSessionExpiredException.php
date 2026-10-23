<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

class StoreSessionExpiredException extends ContenaHttpException
{
    public function __construct()
    {
        parent::__construct('Store session has expired');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_SESSION_EXPIRED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
