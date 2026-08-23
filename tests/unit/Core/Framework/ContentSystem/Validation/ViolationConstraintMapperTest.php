<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Diagnostics\Violation;
use Contena\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Contena\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;

/**
 * @internal
 */
#[CoversClass(ViolationConstraintMapper::class)]
class ViolationConstraintMapperTest extends TestCase
{
    #[TestDox('addresses a property-scoped violation by /{elementId}/{key} and carries the code')]
    public function testMapsPropertyScopedViolation(): void
    {
        $list = new ViolationConstraintMapper()->toConstraintViolationList([
            new Violation(ViolationCode::UnresolvedRequired, 'el-1', 'blog', 'missing'),
        ]);

        static::assertCount(1, $list);
        static::assertSame('/el-1/blog', $list->get(0)->getPropertyPath());
        static::assertSame('unresolved_required', $list->get(0)->getCode());
        static::assertSame('missing', $list->get(0)->getMessage());
    }

    #[TestDox('addresses an element-scoped violation by /{elementId} when there is no key')]
    public function testMapsElementScopedViolation(): void
    {
        $list = new ViolationConstraintMapper()->toConstraintViolationList([
            new Violation(ViolationCode::DuplicateElementId, 'el-1', null, 'duplicate'),
        ]);

        static::assertSame('/el-1', $list->get(0)->getPropertyPath());
    }

    #[TestDox('maps every violation in the batch')]
    public function testMapsEveryViolation(): void
    {
        $list = new ViolationConstraintMapper()->toConstraintViolationList([
            new Violation(ViolationCode::DuplicateElementId, 'a', null, 'dup'),
            new Violation(ViolationCode::UnregisteredComponent, 'b', null, 'unregistered'),
        ]);

        static::assertCount(2, $list);
    }

    #[TestDox('returns an empty constraint-violation list when there are no violations')]
    public function testMapsEmptyViolationList(): void
    {
        $list = new ViolationConstraintMapper()->toConstraintViolationList([]);

        static::assertCount(0, $list);
    }
}
