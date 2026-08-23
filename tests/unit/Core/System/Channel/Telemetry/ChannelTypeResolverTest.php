<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\System\Channel\Telemetry\ChannelTypeResolver;

/**
 * @internal
 */
#[CoversClass(ChannelTypeResolver::class)]
class ChannelTypeResolverTest extends TestCase
{
    #[DataProvider('typeProvider')]
    public function testResolve(string $typeId, string $expected): void
    {
        static::assertSame($expected, new ChannelTypeResolver()->resolve($typeId));
    }

    public static function typeProvider(): \Generator
    {
        yield 'web' => [Defaults::CHANNEL_TYPE_WEB, 'web'];
        yield 'api' => [Defaults::CHANNEL_TYPE_API, 'api'];

        yield 'unknown type id falls back to other' => ['unknown-type-id', 'other'];
        yield 'empty type id falls back to other' => ['', 'other'];
    }
}
