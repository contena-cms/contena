<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer;

/**
 * Controls visibility only. Writes are always restricted to the exact scope
 * carried by the context.
 *
 * @internal
 */
enum DataScopeReadMode: string
{
    case Exact = 'exact';
    case All = 'all';
}
