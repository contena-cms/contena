<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Struct\Fixture;

use Contena\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<AssignTestStruct>
 */
class AssignTestCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return AssignTestStruct::class;
    }
}
