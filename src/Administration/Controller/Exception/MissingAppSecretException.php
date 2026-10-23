<?php declare(strict_types=1);

namespace Contena\Administration\Controller\Exception;

use Contena\Core\Framework\ContenaHttpException;

class MissingAppSecretException extends ContenaHttpException
{
    public function __construct()
    {
        parent::__construct('Failed to retrieve app secret.');
    }

    public function getErrorCode(): string
    {
        return 'ADMINISTRATION__MISSING_APP_SECRET';
    }
}
