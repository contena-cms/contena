<?php declare(strict_types=1);

namespace Contena\Core\Installer\Requirements;

use Contena\Core\Installer\Requirements\Struct\RequirementsCheckCollection;

/**
 * @internal
 */
interface RequirementsValidatorInterface
{
    public function validateRequirements(RequirementsCheckCollection $checks): RequirementsCheckCollection;
}
