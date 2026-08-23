<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Struct\CloneTrait;
use Contena\Tests\Unit\Core\Framework\Struct\Fixture\CloneStruct;
use Contena\Tests\Unit\Core\Framework\Struct\Fixture\CloneStructBackedEnum;
use Contena\Tests\Unit\Core\Framework\Struct\Fixture\CloneStructUnitEnum;

/**
 * @internal
 */
#[CoversClass(CloneTrait::class)]
class CloneStructTest extends TestCase
{
    public function testClone(): void
    {
        $nestedStruct = new CloneStruct();
        $nestedStruct->backedEnum = CloneStructBackedEnum::Case;
        $nestedStruct->unitEnum = CloneStructUnitEnum::Case;

        $original = new CloneStruct();
        $original->arrayOfStructs = [$nestedStruct];
        $original->backedEnum = CloneStructBackedEnum::Case;
        $original->nestedStruct = $nestedStruct;
        $original->unitEnum = CloneStructUnitEnum::Case;

        $clone = clone $original;

        static::assertEquals($original, $clone);
        static::assertNotSame($original, $clone);

        static::assertNotSame($original->arrayOfStructs[0], $clone->arrayOfStructs[0]);
        static::assertNotSame($original->nestedStruct, $clone->nestedStruct);
    }
}
