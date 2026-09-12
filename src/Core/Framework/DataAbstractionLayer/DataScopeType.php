<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer;

/**
 * Identifies the owner represented by a data scope.
 *
 * @internal
 */
enum DataScopeType: string
{
    case Platform = 'platform';
    case Tenant = 'tenant';
}
