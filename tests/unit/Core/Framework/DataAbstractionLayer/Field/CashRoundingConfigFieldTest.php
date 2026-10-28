<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CashRoundingConfigField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CashRoundingConfigField::class)]
class CashRoundingConfigFieldTest extends TestCase
{
    public function testConstructorConfiguresTheRoundingPropertyMapping(): void
    {
        $field = new CashRoundingConfigField('rounding', 'itemRounding');

        static::assertSame('rounding', $field->getStorageName());
        static::assertSame('itemRounding', $field->getPropertyName());

        $mapping = [];
        foreach ($field->getPropertyMapping() as $embedded) {
            $mapping[$embedded->getPropertyName()] = $embedded;
        }

        static::assertSame(['decimals', 'interval', 'roundForNet'], array_keys($mapping));
        static::assertInstanceOf(IntField::class, $mapping['decimals']);
        static::assertInstanceOf(FloatField::class, $mapping['interval']);
        static::assertInstanceOf(BoolField::class, $mapping['roundForNet']);

        foreach ($mapping as $embedded) {
            static::assertTrue($embedded->is(Required::class));
        }
    }
}
