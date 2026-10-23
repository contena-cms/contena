<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Exception;

use Contena\Core\Framework\ContenaHttpException;

class StoreNotAvailableException extends ContenaHttpException
{
    public function __construct()
    {
        parent::__construct('Store is not available');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_NOT_AVAILABLE';
    }
}
