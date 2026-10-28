<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CheapestPriceField::class)]
class CheapestPriceFieldTest extends TestCase
{
    public function testTheFieldIsWriteProtected(): void
    {
        $field = new CheapestPriceField('cheapest_price', 'cheapestPrice');

        static::assertSame('cheapest_price', $field->getStorageName());
        static::assertSame('cheapestPrice', $field->getPropertyName());
        static::assertTrue($field->is(WriteProtected::class));
    }
}
