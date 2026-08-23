<?php declare(strict_types=1);

namespace Contena\Core\System\Tenant\Resolver;

use Contena\Core\Framework\Struct\Struct;

/**
 * The outcome of a tenant resolver: which tenant the request is bound to and
 * where the binding came from. The presence of a resolution itself signals
 * that the request is tenant-bound.
 */
final class TenantResolution extends Struct
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $source,
    ) {
    }
}
