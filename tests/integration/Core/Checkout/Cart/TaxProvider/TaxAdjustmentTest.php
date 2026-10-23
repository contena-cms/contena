<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\TaxProvider;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\AmountCalculator;
use Contena\Core\Checkout\Cart\TaxProvider\TaxAdjustment;
use Contena\Core\Checkout\Cart\TaxProvider\TaxAdjustmentCalculator;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class TaxAdjustmentTest extends TestCase
{
    use KernelTestBehaviour;

    public function testUsesCorrectCalculator(): void
    {
        $adjustment = static::getContainer()->get(TaxAdjustment::class);
        $ref = new \ReflectionClass(TaxAdjustment::class);

        static::assertTrue($ref->hasProperty('amountCalculator'));

        $calculator = $ref->getProperty('amountCalculator')->getValue($adjustment);

        static::assertInstanceOf(AmountCalculator::class, $calculator);

        $ref = new \ReflectionClass($calculator);

        static::assertTrue($ref->hasProperty('taxCalculator'));

        $taxCalculator = $ref->getProperty('taxCalculator')->getValue($calculator);

        static::assertInstanceOf(TaxAdjustmentCalculator::class, $taxCalculator);
    }
}
