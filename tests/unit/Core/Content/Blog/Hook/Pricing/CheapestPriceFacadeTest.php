<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Hook\Pricing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Facade\PriceFacade;
use Contena\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Contena\Core\Checkout\Cart\Price\CashRounding;
use Contena\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Contena\Core\Checkout\Cart\Price\NetPriceCalculator;
use Contena\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Contena\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Cart\Tax\TaxCalculator;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Contena\Core\Content\Blog\Hook\Pricing\CheapestPriceFacade;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
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
            new Price(Defaults::CURRENCY, 2, 5, false),
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

    private function rampUpPriceFacade(IdsCollection $ids, string $currencyKey, string $taxState): CheapestPriceFacade
    {
        $entity = new class extends Entity {
            protected CalculatedPrice $calculatedPrice;
        };

        $quantityCalculator = new QuantityPriceCalculator(
            new GrossPriceCalculator(new TaxCalculator(), new CashRounding()),
            new NetPriceCalculator(new TaxCalculator(), new CashRounding())
        );

        $stubs = new ScriptPriceStubs(
            // not necessary for this test
            static::createStub(Connection::class),
            $quantityCalculator,
            new PercentagePriceCalculator(new CashRounding(), $quantityCalculator, new PercentageTaxRuleBuilder()),
        );

        $entity->assign(['calculatedPrice' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection())]);

        $original = new CalculatedCheapestPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(new TaxRuleCollection([new TaxRule(10)])));

        // mock context to simulate currency and tax states
        $context = static::createStub(ChannelContext::class);

        // currency key will be provided, we want to test different currencies are taking into account
        $context->method('getCurrencyId')->willReturn($ids->get($currencyKey));

        // we also want to test different tax states (gross/net)
        $context->method('getTaxState')->willReturn($taxState);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        return new CheapestPriceFacade($entity, $original, $stubs, $context);
    }
}
