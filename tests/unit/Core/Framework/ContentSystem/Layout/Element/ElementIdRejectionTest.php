<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Layout\Element;

use Contena\Core\Framework\ContentSystem\Layout\Element\ElementIdRejection;
use Contena\Core\Framework\ContentSystem\Layout\Element\ElementIdRule;
use Contena\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * A case nothing returns is dead, and a clause no case names cannot be worded by the two enforcement sites.
 * Each words the verdict through a `match` that would throw on an unhandled one.
 *
 * @internal
 */
#[CoversClass(ElementIdRejection::class)]
class ElementIdRejectionTest extends TestCase
{
    #[TestDox('every declared rejection is reachable from the rule, and the rule reaches no other')]
    public function testTheCasesAndTheRuleOutcomesAreTheSameSet(): void
    {
        $reached = array_map(
            static fn (string $id): ?ElementIdRejection => ElementIdRule::rejection($id),
            [VirtualRootWrapper::VIRTUAL_ROOT_ID, '12', "hero\nfoot"]
        );

        static::assertEqualsCanonicalizing(ElementIdRejection::cases(), $reached);
    }
}
