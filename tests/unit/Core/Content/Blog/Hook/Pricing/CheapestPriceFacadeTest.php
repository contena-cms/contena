<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Hook\Pricing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Facade\PriceFacade;
use Contena\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Content\Blog\Hook\Pricing\CheapestPriceFacade;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(CheapestPriceFacade::class)]
class CheapestPriceFacadeTest extends TestCase
{
    #[DataProvider('providerChange')]
    public function testChange(string $currencyKey, string $taxState, float $unit, float $tax): void
    {
        $ids = new IdsCollection([
            'default' => Defaults::CURRENCY,
            'usd' => Uuid::randomHex(),
        ]);

        $price = $this->rampUpPriceFacade($ids, $currencyKey, $taxState);

        $update = new PriceCollection([
            new Price($ids->get('usd'), 1, 4, false),
        ]);

        $price->change($update);

        static::assertSame($unit, $price->getUnit());
        static::assertSame($tax, $price->getTaxes()->getAmount());
    }

    public function testChangeWithPriceFacade(): void
    {
        $ids = new IdsCollection([
            'default' => Defaults::CURRENCY,
            'usd' => Uuid::randomHex(),
        ]);

        $price = $this->rampUpPriceFacade($ids, 'default', CartPrice::TAX_STATE_GROSS);

        $price->change(
            new PriceFacade(
                new Entity(),
                new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection()),
                static::createStub(ScriptPriceStubs::class),
                static::createStub(ChannelContext::class)
            )
        );

        static::assertSame(5.0, $price->getUnit());
    }

    public function testChangeWithNullFacade(): void
    {
        $ids = new IdsCollection([
            'default' => Defaults::CURRENCY,
            'usd' => Uuid::randomHex(),
        ]);

        $price = $this->rampUpPriceFacade($ids, 'default', CartPrice::TAX_STATE_GROSS);

        $price->change(null);

        static::assertSame(10.0, $price->getUnit());
    }

    public function testReset(): void
    {
        $ids = new IdsCollection([
            'default' => Defaults::CURRENCY,
            'usd' => Uuid::randomHex(),
        ]);

        $price = $this->rampUpPriceFacade($ids, 'default', CartPrice::TAX_STATE_GROSS);

        $price->reset();

        static::assertSame(10.0, $price->getUnit());
    }

    public static function providerChange(): \Generator
    {
        yield 'Test default currency' => ['default', CartPrice::TAX_STATE_GROSS, 5.0, 0.45];
        yield 'Test usd currency' => ['usd', CartPrice::TAX_STATE_GROSS, 4.0, 0.36];

        yield 'Test net default currency' => ['default', CartPrice::TAX_STATE_NET, 2.0, 0.2];
        yield 'Test net usd currency' => ['usd', CartPrice::TAX_STATE_NET, 1.0, 0.1];
    }

}
