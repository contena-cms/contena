<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataContext;

use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ContextType::class)]
class ContextTypeTest extends TestCase
{
    #[TestDox('returns all case values as list of strings')]
    public function testValuesReturnsAllCaseValues(): void
    {
        $values = ContextType::values();

        static::assertSame(['single', 'collection'], $values);
    }
}
