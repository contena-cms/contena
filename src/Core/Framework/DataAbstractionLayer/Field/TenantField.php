<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\TenantFieldSerializer;

/**
 * Marks an entity as platform-or-tenant scoped. A null value
 * identifies platform-owned data; a tenant id identifies tenant-owned data.
 * Tenant reads are filtered automatically and tenant writes inherit the current
 * tenant. Platform global contexts read both modes but only write platform data.
 * Entities without this field are shared platform infrastructure.
 *
 * The field is not API aware, so business payloads can neither read nor write
 * it directly.
 */
class TenantField extends FkField
{
    public function __construct(string $storageName = 'tenant_id', string $propertyName = 'tenantId')
    {
        parent::__construct($storageName, $propertyName, 'tenant');
    }

    protected function getSerializerClass(): string
    {
        return TenantFieldSerializer::class;
    }
}
