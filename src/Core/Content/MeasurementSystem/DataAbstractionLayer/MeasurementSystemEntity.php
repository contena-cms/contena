<?php declare(strict_types=1);

namespace Contena\Core\Content\MeasurementSystem\DataAbstractionLayer;

use Contena\Core\Framework\DataAbstractionLayer\Attribute\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Contena\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\ArrayEntity;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
#[Entity('measurement_system', since: '6.7.1.0')]
class MeasurementSystemEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;

    #[Field(type: FieldType::STRING, api: true)]
    public string $technicalName;

    #[Field(type: FieldType::STRING, translated: true, api: true)]
    public ?string $name = null;

    /**
     * @var array<string, MeasurementDisplayUnitEntity>|null
     */
    #[OneToMany(entity: 'measurement_display_unit', ref: 'measurement_system_id', onDelete: OnDelete::CASCADE, api: true)]
    public ?array $units = null;

    /**
     * @var array<string, ArrayEntity>|null
     */
    #[Translations]
    public ?array $translations = null;

    /**
     * @var array<mixed>|null
     */
    #[CustomFields(true)]
    protected ?array $customFields = null;
}
