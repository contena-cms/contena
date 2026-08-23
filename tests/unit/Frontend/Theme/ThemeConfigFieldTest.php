<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Theme\ThemeConfigField;

/**
 * @internal
 */
#[CoversClass(ThemeConfigField::class)]
class ThemeConfigFieldTest extends TestCase
{
    /**
     * @param array<array-key, mixed>|bool|float|int|string|null $value
     */
    #[DataProvider('supportedValues')]
    public function testSetValueAcceptsSupportedValues(array|bool|float|int|string|null $value): void
    {
        $field = new ThemeConfigField();
        $field->setValue($value);

        static::assertSame($value, $field->getValue());
    }

    public static function supportedValues(): \Generator
    {
        yield 'array' => [['value']];
        yield 'boolean' => [true];
        yield 'float' => [1.5];
        yield 'integer' => [1];
        yield 'string' => ['value'];
        yield 'null for an emptied value' => [null];
    }
}
