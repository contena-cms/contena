<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\System\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Maintenance\MaintenanceException;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class DatabaseSetupException extends MaintenanceException
{
}
