<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Struct\Fixture;

use Contena\Core\Framework\Struct\CloneTrait;

/**
 * @internal
 */
class CloneStruct
{
    use CloneTrait;

    /**
     * @var array<array-key, CloneStruct>
     */
    public array $arrayOfStructs;

    public CloneStructBackedEnum $backedEnum;

    public CloneStructUnitEnum $unitEnum;

    public CloneStruct $nestedStruct;
}
