<?php declare(strict_types=1);

namespace Contena\Core\System\Tenant;

use Contena\Core\Framework\DataAbstractionLayer\Attribute\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;

/**
 * A tenant is the data-isolation boundary of the platform. Tenants are not
 * tenant-scoped themselves and are managed by the platform only.
 *
 * @internal
 */
#[Entity('tenant', since: '6.8.0.0')]
class TenantEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;

    #[Field(type: FieldType::STRING, api: true)]
    public string $name;

    #[Field(type: FieldType::STRING, api: true, maxLength: 64)]
    public string $code;

    #[Field(type: FieldType::BOOL, api: true)]
    public bool $status;

    /**
     * @var array<mixed>|null
     */
    #[CustomFields]
    protected ?array $customFields = null;
}
