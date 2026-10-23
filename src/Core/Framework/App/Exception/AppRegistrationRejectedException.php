<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Exception;

use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
class AppRegistrationRejectedException extends AppRegistrationException
{
    public function getErrorCode(): string
    {
        return AppException::APP_REGISTRATION_REJECTED;
    }
}
