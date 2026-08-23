<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\NumberRange\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\NumberRange\Telemetry\NumberRangeTypeResolver;

/**
 * @internal
 */
#[CoversClass(NumberRangeTypeResolver::class)]
class NumberRangeTypeResolverTest extends TestCase
{
    #[DataProvider('technicalNameProvider')]
    public function testResolve(?string $technicalName, string $expected): void
    {
        static::assertSame($expected, new NumberRangeTypeResolver()->resolve($technicalName));
    }

    /**
     * @return \Generator<string, array{0: ?string, 1: string}>
     */
    public static function technicalNameProvider(): \Generator
    {
        // core type technical names map to their bounded group
        yield 'member maps to member' => ['member', 'member'];
        yield 'user maps to user' => ['user', 'user'];

        // unmapped inputs fall through to other
        yield 'null maps to other' => [null, 'other'];
        yield 'empty string maps to other' => ['', 'other'];
        yield 'plugin custom range maps to other' => ['my_plugin_range', 'other'];
    }
}
