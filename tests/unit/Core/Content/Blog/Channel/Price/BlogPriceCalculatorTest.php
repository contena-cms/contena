<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceCollection;
use Contena\Core\Content\Blog\Channel\Price\BlogPriceCalculator;
use Contena\Core\Content\Blog\Extension\BlogPriceCalculationExtension;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Test\TestCaseHelper\CallableClass;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Unit\UnitCollection;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(BlogPriceCalculator::class)]
class BlogPriceCalculatorTest extends TestCase
{

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();

        $unitRepository = new StaticEntityRepository([
        ]);

        $this->calculator = new BlogPriceCalculator(
            $unitRepository,
            new ExtensionDispatcher($this->eventDispatcher),
        );
    }

    public function testExtensionIsDispatched(): void
    {
        $pre = $this->createMock(CallableClass::class);
        $pre->expects($this->once())->method('__invoke');
        $this->eventDispatcher->addListener(BlogPriceCalculationExtension::NAME . '.pre', $pre);

        $post = $this->createMock(CallableClass::class);
        $post->expects($this->once())->method('__invoke');
        $this->eventDispatcher->addListener(BlogPriceCalculationExtension::NAME . '.post', $post);

        $this->calculator->calculate([], static::createStub(ChannelContext::class));
    }


    #[DataProvider('taxStateWillBeUsedProvider')]
    public function testTaxStateWillBeUsed(Entity $blog, string $state, float $expected): void
    {
        $context = static::createStub(ChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getTaxState')->willReturn($state);
        $context->method('buildTaxRules')->willReturn(new TaxRuleCollection([new TaxRule(10)]));
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        $this->calculator->calculate([$blog], $context);

        $price = $blog->get('calculatedPrice');

        static::assertInstanceOf(CalculatedPrice::class, $price);

        static::assertSame($expected, $price->getTotalPrice());
    }

    public static function taxStateWillBeUsedProvider(): \Generator
    {
        $blog = (new PartialEntity())->assign([
            'taxId' => Uuid::randomHex(),
            'price' => new PriceCollection([
            ]),
        ]);

        yield 'Gross price will be used for gross state' => [$blog, CartPrice::TAX_STATE_GROSS, 20];

        yield 'Net price will be used for net price state' => [$blog, CartPrice::TAX_STATE_NET, 10];

        yield 'Net price will be used for tax free state' => [$blog, CartPrice::TAX_STATE_FREE, 10];
    }

    public function testUnitsAreLoadedOnceUntilReset(): void
    {
        // every unit search consumes one queued result, so the queue length counts the loads
        $unitRepository = new StaticEntityRepository([new UnitCollection(), new UnitCollection()]);
        $calculator = new BlogPriceCalculator(
            $unitRepository,
            new ExtensionDispatcher($this->eventDispatcher),
        );
        $context = static::createStub(ChannelContext::class);

        $calculator->calculate([], $context);
        $calculator->calculate([], $context);
        static::assertCount(1, $unitRepository->searches, 'units are cached after the first calculation');

        $calculator->reset();
        $calculator->calculate([], $context);
        static::assertCount(0, $unitRepository->searches, 'reset() drops the cached units');
    }

    public function testCoreServiceThrowsDecorationException(): void
    {
        $this->expectException(DecorationPatternException::class);

        (new BlogPriceCalculator(
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher($this->eventDispatcher)
        ))->getDecorated();
    }

    public static function priceWillBeCalculated(): \Generator
    {
        yield 'Price will not be calculated without tax id' => [
            new PartialEntity(),
            null,
        ];

        yield 'Price will not be calculated without price loaded' => [
            (new PartialEntity())->assign(['taxId' => Uuid::randomHex()]),
            null,
        ];

        yield 'Price will be calculated' => [
        ];

        yield 'Reference price will be calculated' => [
        ];

        yield 'Reference price will be not calculated, if the unit not found' => [
        ];

        yield 'List price will be calculated' => [
        ];

        yield 'Regulation price will be calculated' => [
        ];

        yield 'Regulation price will be not skipped when equals' => [
        ];
    }

    /**
     * @param array<int, float> $expected
     */
    #[DataProvider('advancedPricesWillBeCalculatedProvider')]
    public function testAdvancedPricesWillBeCalculated(Entity $blog, array $expected): void
    {
        $context = static::createStub(ChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getRuleIds')->willReturn([Defaults::CURRENCY]);
        $context->method('buildTaxRules')->willReturn(new TaxRuleCollection([new TaxRule(19)]));

        $this->calculator->calculate([$blog], $context);

        if ($expected === []) {
            static::assertCount(0, $blog->get('calculatedPrices'));

            return;
        }

        $prices = $blog->get('calculatedPrices');

        static::assertInstanceOf(CalculatedPriceCollection::class, $prices);

        static::assertCount(\count($expected), $prices);

        foreach ($expected as $index => $value) {
            static::assertTrue($prices->has($index));

            $price = $prices->get($index);

            static::assertSame($value, $price->getTotalPrice());
        }
    }

    public function testFilterRulePricesReturnsFirstMatchingContextRuleInOrder(): void
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $context = static::createStub(ChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        // ruleA takes precedence because it is listed first in the context rule ids
        $context->method('getRuleIds')->willReturn([$ruleA, $ruleB]);
        $context->method('buildTaxRules')->willReturn(new TaxRuleCollection([new TaxRule(19)]));

        $blog = (new PartialEntity())->assign([
            'id' => Uuid::randomHex(),
            'taxId' => Uuid::randomHex(),
            'prices' => new BlogPriceCollection([
            ]),
        ]);

        $this->calculator->calculate([$blog], $context);

        $prices = $blog->get('calculatedPrices');

        static::assertInstanceOf(CalculatedPriceCollection::class, $prices);
        static::assertCount(1, $prices);
        // 1.0 proves ruleA won; the regression would pick ruleB (5.0)
        static::assertSame(1.0, $prices->first()?->getTotalPrice());
    }

    public function testFilterRulePricesSkipsContextRulesWithoutMatchingPrices(): void
    {
        $ruleWithoutPrices = Uuid::randomHex();
        $ruleWithPrices = Uuid::randomHex();

        $context = static::createStub(ChannelContext::class);
        $context->method('getCurrencyId')->willReturn(Defaults::CURRENCY);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        // the first context rule has no price, the calculator must fall through to the next
        $context->method('getRuleIds')->willReturn([$ruleWithoutPrices, $ruleWithPrices]);
        $context->method('buildTaxRules')->willReturn(new TaxRuleCollection([new TaxRule(19)]));

        $blog = (new PartialEntity())->assign([
            'id' => Uuid::randomHex(),
            'taxId' => Uuid::randomHex(),
            'prices' => new BlogPriceCollection([
            ]),
        ]);

        $this->calculator->calculate([$blog], $context);

        $prices = $blog->get('calculatedPrices');

        static::assertInstanceOf(CalculatedPriceCollection::class, $prices);
        static::assertCount(1, $prices);
        static::assertSame(1.0, $prices->first()?->getTotalPrice());
    }

    public static function advancedPricesWillBeCalculatedProvider(): \Generator
    {
        yield 'Prices will not be calculated when not loaded' => [
            (new PartialEntity())->assign(['id' => Uuid::randomHex(), 'prices' => null]),
            [],
        ];

        yield 'Partial entity price collection will be calculated' => [
            (new PartialEntity())->assign([
                'id' => Uuid::randomHex(),
                'taxId' => Uuid::randomHex(),
                'prices' => new EntityCollection([
                ]),
            ]),
            [1.0],
        ];

        yield 'Only matching rule ids will be calculated' => [
            (new PartialEntity())->assign([
                'id' => Uuid::randomHex(),
                'taxId' => Uuid::randomHex(),
                'prices' => new BlogPriceCollection([
                ]),
            ]),
            [],
        ];

        yield 'Blog will be calculated when price collection loaded' => [
            (new PartialEntity())->assign([
                'id' => Uuid::randomHex(),
                'taxId' => Uuid::randomHex(),
                'prices' => new BlogPriceCollection([
                ]),
            ]),
            [1.0],
        ];
    }


    public static function cheapestPriceWillBeCalculatedProvider(): \Generator
    {
        yield 'Cheapest price calculation uses the price object' => [
        ];

        yield 'Cheapest price calculation uses the cheapest price container' => [
        ];
    }
}

/**
 * @internal
 */
class PriceAssertion
{
    public function __construct(
        public float $price,
        public ?float $listPrice = null,
        public ?float $reference = null,
        public ?float $regulation = null
    ) {
    }
}
