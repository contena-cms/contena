<?php declare(strict_types=1);

namespace Contena\Core\Service\ServiceRegistry;

use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('framework')]
enum ServiceStatus: string
{
    case ACTIVE = 'active';
    case RETIRED = 'retired';
}
