<?php declare(strict_types=1);

namespace Contena\Core\System\DataScope;

use Contena\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\DataScopeType;
use Contena\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;

/**
 * Foreign-key target for every scope-owned business row.
 *
 * Scope records are immutable infrastructure and are not exposed through the
 * generic API. Tenant scope ids equal the corresponding tenant ids.
 *
 * @internal
 */
#[Entity('data_scope', since: '6.8.0.0')]
class DataScopeEntity extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: false)]
    public string $id;

    #[Field(type: FieldType::STRING, api: false, maxLength: 16)]
    public string $type;

    public function getType(): DataScopeType
    {
        return DataScopeType::from($this->type);
    }
}
