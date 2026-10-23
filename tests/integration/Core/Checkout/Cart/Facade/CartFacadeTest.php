<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Facade;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\Facade\CartFacade;
use Contena\Core\Checkout\Cart\Facade\CartFacadeHookFactory;
use Contena\Core\Checkout\Cart\Facade\ContainerFacade;
use Contena\Core\Checkout\Cart\Facade\ItemFacade;
use Contena\Core\Checkout\Cart\Facade\ItemsFacade;
use Contena\Core\Checkout\Cart\Facade\PriceFacade;
use Contena\Core\Checkout\Cart\Hook\CartHook;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\Script;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\Framework\Test\Script\Execution\TestHook;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\AppSystemTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class CartFacadeTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private Script $script;

    protected function setUp(): void
    {
        parent::setUp();

        $this->init();
        $this->script = new Script('test', '', new \DateTimeImmutable());
    }

    #[DataProvider('addBlogProvider')]
    public function testAddBlog(string $input, ?string $expected): void
    {
        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, []);

        $hook = new CartHook($this->createCart(), $context);

        $service = static::getContainer()->get(CartFacadeHookFactory::class)
            ->factory($hook, $this->script);

        $service->blogs()->add($this->ids->get($input));
        $service->calculate();

        $item = $service->blogs()->get($this->ids->get($input));

        if ($expected === null) {
            static::assertNull($item);

            return;
        }

        static::assertInstanceOf(ItemFacade::class, $item);
        static::assertSame($this->ids->get($expected), $item->getReferencedId());
        static::assertSame(LineItem::BLOG_LINE_ITEM_TYPE, $item->getType());
    }

    public function testContainer(): void
    {
        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, []);

        $hook = new CartHook($this->createCart(), $context);

        $service = static::getContainer()->get(CartFacadeHookFactory::class)
            ->factory($hook, $this->script);

        $id = $this->ids->get('p1');

        $blog = $service->blogs()->add($id, 10);

        $container = $service->container('my-container');

        static::assertInstanceOf(ItemFacade::class, $blog);
        static::assertInstanceOf(ContainerFacade::class, $container);

        $split = $blog->take(1);
        static::assertInstanceOf(ItemFacade::class, $split);
        $container->add($split);

        $split = $blog->take(1);
        static::assertInstanceOf(ItemFacade::class, $split);
        $container->add($split);
        $container->discount('my-discount', 'percentage', -10, 'Fancy discount');

        $surcharge = new PriceCollection([new Price(Defaults::CURRENCY, 2, 2, false)]);
        $container->surcharge('my-surcharge', 'absolute', $surcharge, 'unit test');

        $service->items()->add($container);
        $service->calculate();

        static::assertTrue($service->has('my-container'));
        $container = $service->get('my-container');

        static::assertInstanceOf(ItemFacade::class, $container);
        static::assertInstanceOf(PriceFacade::class, $container->getPrice());
        static::assertSame(182.0, $container->getPrice()->getTotal());
    }

    public function testRemove(): void
    {
        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $hook = new CartHook($this->createCart(), $context);
        $cart = static::getContainer()->get(CartFacadeHookFactory::class)->factory($hook, $this->script);

        $item = $cart->blogs()->add($this->ids->get('p1'));

        static::assertInstanceOf(ItemFacade::class, $item);
        static::assertSame($this->ids->get('p1'), $item->getReferencedId());
        static::assertSame(LineItem::BLOG_LINE_ITEM_TYPE, $item->getType());

        $cart->remove($item->getId());

        static::assertNull($cart->get($this->ids->get('p1')));
        $cart->remove('not-existing');
    }

    /**
     * @param array<string, ExpectedPrice|array<string, array<string, array<string, array<string, ExpectedPrice>|ExpectedPrice>|ExpectedPrice>|ExpectedPrice>|null> $expectations
     */
    #[DataProvider('scriptProvider')]
    public function testScripts(string $hook, array $expectations, ?\Closure $closure = null): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $hook = $this->createTestHook($hook);

        $service = static::getContainer()
            ->get(CartFacadeHookFactory::class)
            ->factory($hook, $this->script);

        static::getContainer()->get(ScriptExecutor::class)->execute($hook);

        $this->assertItems($service, $expectations);

        if ($closure instanceof \Closure) {
            $closure($service, $this->ids);
        }
    }

    public function testDependency(): void
    {
        $this->expectException(CartException::class);

        $service = static::getContainer()->get(CartFacadeHookFactory::class);
        $service->factory(new TestHook('test', Context::createDefaultContext()), $this->script);
    }

    public static function addBlogProvider(): \Generator
    {
        yield 'Test with simple blog' => ['p1', 'p1'];
        yield 'Test variant support' => ['v2.1', 'v2.1'];
        yield 'Test parents will not be added' => ['p2', null];
    }

    public static function scriptProvider(): \Generator
    {
        yield 'Test add blog case' => [
            'add-blog-cases',
            [
                'p1' => new ExpectedPrice(100),
                'v2.1' => new ExpectedPrice(100),
                'p2' => null,
            ],
        ];

        yield 'Test remove blog' => [
            'remove-blog-cases',
            [
                'p1' => null,
                'v2.1' => new ExpectedPrice(100),
            ],
        ];

        yield 'Add simple discount' => [
            'add-simple-discount',
            [
                'p1' => new ExpectedPrice(100),
                'my-discount' => new ExpectedPrice(-10),
            ],
        ];

        yield 'Add simple surcharge' => [
            'add-simple-surcharge',
            [
                'p1' => new ExpectedPrice(100),
                'my-surcharge' => new ExpectedPrice(10),
            ],
        ];

        yield 'Add discount for stacked items' => [
            'add-discount-for-stacked-items',
            [
                'p1' => new ExpectedPrice(100, 300),
                'my-discount' => new ExpectedPrice(-30),
            ],
        ];

        yield 'Add surcharge for stacked items' => [
            'add-surcharge-for-stacked-items',
            [
                'p1' => new ExpectedPrice(100, 300),
                'my-surcharge' => new ExpectedPrice(30),
            ],
        ];

        yield 'Add discount for multiple items' => [
            'add-discount-for-multiple-items',
            [
                'p1' => new ExpectedPrice(100),
                'v2.1' => new ExpectedPrice(100),
                'my-discount' => new ExpectedPrice(-20),
            ],
        ];

        yield 'Add surcharge for multiple items' => [
            'add-surcharge-for-multiple-items',
            [
                'p1' => new ExpectedPrice(100),
                'v2.1' => new ExpectedPrice(100),
                'my-surcharge' => new ExpectedPrice(20),
            ],
        ];

        yield 'Add absolute discount' => [
            'add-absolute-discount',
            [
                'p1' => new ExpectedPrice(100),
                'my-discount' => new ExpectedPrice(-19.99),
            ],
        ];

        yield 'Add absolute surcharge' => [
            'add-absolute-surcharge',
            [
                'p1' => new ExpectedPrice(100),
                'my-surcharge' => new ExpectedPrice(19.99),
            ],
        ];

        yield 'Test split blog' => [
            'split-blog',
            [
                'p1' => new ExpectedPrice(100, 300),
                'new-key' => new ExpectedPrice(100, 200),
            ],
        ];

        yield 'Test add container' => [
            'add-container',
            [
                'p1' => new ExpectedPrice(100, 300),
                'my-container' => [
                    'price' => new ExpectedPrice(180),
                    'children' => [
                        'first' => new ExpectedPrice(100),
                        'second' => new ExpectedPrice(100),
                        'discount' => new ExpectedPrice(-20),
                    ],
                ],
            ],
        ];

        yield 'Test nested containers' => [
            'add-nested-container',
            [
                'p1' => new ExpectedPrice(100),
                'my-container' => [
                    'price' => new ExpectedPrice(315),
                    'children' => [
                        'first' => new ExpectedPrice(100),
                        'second' => new ExpectedPrice(100),
                        'discount' => new ExpectedPrice(-35),
                        'nested' => [
                            'price' => new ExpectedPrice(150),
                            'children' => [
                                'third' => new ExpectedPrice(100),
                                'fourth' => new ExpectedPrice(100),
                                'absolute' => new ExpectedPrice(-50),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'Test payload' => [
            'payload-cases',
            [],
            static function (CartFacade $service, IdsCollection $ids): void {
                $item = $service->get($ids->get('p1'));
                static::assertInstanceOf(ItemFacade::class, $item);

                $expected = ['test' => 1, 'foo' => 'bar', 'bar' => 'baz', 'baz' => true];
                foreach ($expected as $key => $value) {
                    static::assertArrayHasKey($key, $item->getItem()->getPayload());
                    $actual = $item->getItem()->getPayload()[$key];
                    static::assertSame($value, $actual, \sprintf('Payload value %s does not match', $key));
                }
            },
        ];

        yield 'Test add errors' => [
            'add-errors',
            [],
            static function (CartFacade $cart): void {
                static::assertTrue($cart->errors()->has('NO_BLOGS_IN_CART'));
                static::assertTrue($cart->errors()->has('YOU_SHOULD_REALLY_ADD_BLOGS'));
                static::assertTrue($cart->errors()->has('ADD_BLOGS_OR_GO_AWAY'));
                static::assertTrue($cart->errors()->has('add-same-message'));
                static::assertTrue($cart->errors()->has('MESSAGE_WITH_PARAMETERS'));
            },
        ];

        yield 'Test cart states' => [
            'cart-state',
            [],
            static function (CartFacade $cart): void {
                static::assertTrue($cart->states()->has('my-custom-state'));
                static::assertFalse($cart->states()->has('default-state'));
            },
        ];

        yield 'Discount blog price' => [
            'discount-blog-price',
            [
                'p1' => new ExpectedPrice(90, 90),
            ],
        ];
    }

    private function createCart(): Cart
    {
        $cart = new Cart('test');
        $cart->setBehavior(new CartBehavior());
        $cart->addState('default-state');

        return $cart;
    }

    /**
     * @param array<string, ExpectedPrice|array<string, array<string, array<string, array<string, ExpectedPrice>|ExpectedPrice>|ExpectedPrice>|ExpectedPrice>|null> $expectations
     */
    private function assertItems(ItemsFacade|CartFacade|LineItemCollection $scope, array $expectations): void
    {
        foreach ($expectations as $key => $expected) {
            if ($expected === null) {
                static::assertFalse($scope->has($key));

                continue;
            }

            if ($this->ids->has($key)) {
                $key = $this->ids->get($key);
            }
            static::assertTrue($scope->has($key), \sprintf('Can not find item %s', $key));
            $item = $scope->get($key);

            if ($expected instanceof CalculatedPrice) {
                static::assertInstanceOf(ItemFacade::class, $item);
                static::assertInstanceOf(PriceFacade::class, $item->getPrice());
                static::assertSame($expected->getUnitPrice(), $item->getPrice()->getUnit());
                static::assertSame($expected->getTotalPrice(), $item->getPrice()->getTotal());

                continue;
            }

            $price = $expected['price'];
            static::assertInstanceOf(ExpectedPrice::class, $price);
            static::assertInstanceOf(ItemFacade::class, $item);
            static::assertInstanceOf(PriceFacade::class, $item->getPrice());
            static::assertSame($price->getUnitPrice(), $item->getPrice()->getUnit(), print_r($item->getItem(), true));
            static::assertSame($price->getTotalPrice(), $item->getPrice()->getTotal());

            $children = $expected['children'];
            static::assertIsArray($children);
            $this->assertItems($item->getChildren(), $children);
        }
    }

    private function createTestHook(string $case): CartTestHook
    {
        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, []);

        $cart = $this->createCart();

        return new CartTestHook($case, $cart, $context, $this->ids, [CartFacadeHookFactory::class]);
    }

    private function init(): IdsCollection
    {
        $this->ids = new IdsCollection();
        $blogs = [
            (new BlogBuilder($this->ids, 'p1'))
                ->price(100)
                ->visibility()
                ->build(),
            (new BlogBuilder($this->ids, 'p2'))
                ->price(100)
                ->variant(
                    (new BlogBuilder($this->ids, 'v2.1'))
                        ->option('red', 'color')
                        ->build()
                )
                ->visibility()
                ->build(),
        ];

        static::getContainer()->get('blog.repository')
            ->create($blogs, Context::createDefaultContext());

        return $this->ids;
    }
}

/**
 * @internal
 */
class ExpectedPrice extends CalculatedPrice
{
    public function __construct(
        float $unitPrice,
        ?float $totalPrice = null,
        ?CalculatedTaxCollection $calculatedTaxes = null,
        ?TaxRuleCollection $taxRules = null,
        int $quantity = 1
    ) {
        $totalPrice ??= $unitPrice;
        $calculatedTaxes ??= new CalculatedTaxCollection([]);
        $taxRules ??= new TaxRuleCollection([]);

        parent::__construct($unitPrice, $totalPrice, $calculatedTaxes, $taxRules, $quantity);
    }
}
