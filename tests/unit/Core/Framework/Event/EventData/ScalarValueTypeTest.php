<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\FrameworkException;
use Contena\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(ScalarValueType::class)]
class ScalarValueTypeTest extends TestCase
{
    public function testToArray(): void
    {
        $expected = [
            'type' => 'float',
        ];

        static::assertSame($expected, (new ScalarValueType(ScalarValueType::TYPE_FLOAT))->toArray());
    }

    public function testThrowExceptionOnInvalidType(): void
    {
        $this->expectExceptionObject(FrameworkException::invalidArgumentException('Invalid type "test" provided, valid ones are: string, int, float, bool'));

        new ScalarValueType('test');
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testThrowExceptionOnInvalidTypeDeprecated(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ScalarValueType('test');
    }
}
