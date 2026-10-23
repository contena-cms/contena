<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\Price\ReferencePriceDto;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(ReferencePriceDto::class)]
class ReferencePriceDtoTest extends TestCase
{
    public function testCreateFromEntity(): void
    {
        $blog = new BlogEntity();
        $blog->setPurchaseUnit(1);
        $blog->setReferenceUnit(1);
        $blog->setUnitId('unit-id');

        static::assertEquals(
            new ReferencePriceDto(1, 1, 'unit-id'),
            ReferencePriceDto::createFromEntity($blog)
        );
    }

    public function testCreateFromCheapestPrice(): void
    {
        $cheapestPrice = new CheapestPrice();
        $cheapestPrice->setPurchase(1);
        $cheapestPrice->setReference(1);
        $cheapestPrice->setUnitId('unit-id');

        static::assertEquals(
            new ReferencePriceDto(1, 1, 'unit-id'),
            ReferencePriceDto::createFromCheapestPrice($cheapestPrice)
        );
    }

    public function testGetter(): void
    {
        $referencePrice = new ReferencePriceDto(1, 2, 'unit-id');

        static::assertSame(1.0, $referencePrice->getPurchase());
        static::assertSame(2.0, $referencePrice->getReference());
        static::assertSame('unit-id', $referencePrice->getUnitId());
    }

    public function testSetter(): void
    {
        $referencePrice = new ReferencePriceDto(1, 2, 'unit-id');
        $referencePrice->setPurchase(3);
        $referencePrice->setReference(4);
        $referencePrice->setUnitId('unit-id-2');

        static::assertSame(3.0, $referencePrice->getPurchase());
        static::assertSame(4.0, $referencePrice->getReference());
        static::assertSame('unit-id-2', $referencePrice->getUnitId());
    }
}
