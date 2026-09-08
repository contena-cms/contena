<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Exception;

use Contena\Core\Framework\App\AppException;

/**
 * Thrown when a manifest is refused. Carries the failing check's own error code, message and
 * parameters, so callers that only report validation problems can catch this without also catching
 * every other way an installation can fail.
 *
 * @internal only for use by the app-system
 */
class AppValidationRefusedException extends AppException
{
}
