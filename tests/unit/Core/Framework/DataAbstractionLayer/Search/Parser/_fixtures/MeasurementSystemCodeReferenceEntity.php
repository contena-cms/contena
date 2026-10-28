<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Parser\_fixtures;

use Contena\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementSystemEntity;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;

/**
 * @internal
 */
#[Entity('measurement_system_code_reference')]
class MeasurementSystemCodeReferenceEntity extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[ForeignKey(entity: 'measurement_system')]
    public ?string $measurementSystemId = null;

    #[ManyToOne(entity: 'measurement_system', ref: 'technical_name')]
    public ?MeasurementSystemEntity $measurementSystem = null;
}
