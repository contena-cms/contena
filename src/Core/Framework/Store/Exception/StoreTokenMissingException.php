<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\StoreException;
use Symfony\Component\HttpFoundation\Response;

class StoreTokenMissingException extends StoreException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_FORBIDDEN,
            self::STORE_TOKEN_IS_MISSING,
            'Store token is missing'
        );
    }
}
