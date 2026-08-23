<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Util\ArrayNormalizer;

/**
 * @internal
 */
#[CoversClass(ArrayNormalizer::class)]
class ArrayNormalizerTest extends TestCase
{
    /**
     * @param array<string, mixed> $nested
     * @param array<string, string> $flattened
     */
    #[DataProvider('provideTestData')]
    public function testFlattening(array $nested, array $flattened): void
    {
        static::assertSame($flattened, ArrayNormalizer::flatten($nested));
    }

    /**
     * @param array<string, mixed> $nested
     * @param array<string, string> $flattened
     */
    #[DataProvider('provideTestData')]
    public function testExpanding(array $nested, array $flattened): void
    {
        static::assertSame($nested, ArrayNormalizer::expand($flattened));
    }

    /**
     * @return list<array{array<string, mixed>, array<string, string>}>
     */
    public static function provideTestData(): array
    {
        return [
            [
                [ // nested
                    'firstName' => 'Foo',
                    'lastName' => 'Bar',
                    'billingAddress' => [
                        'street' => 'Foostreet',
                    ],
                ],
                [ // flattened
                    'firstName' => 'Foo',
                    'lastName' => 'Bar',
                    'billingAddress.street' => 'Foostreet',
                ],
            ],
        ];
    }
}
