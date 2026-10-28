<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Requirements;

use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractRequirement implements Requirement
{
    public function required(Manifest $manifest): bool
    {
        return \in_array(static::name(), $manifest->getRequirements(), true);
    }
}
