<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Cart;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\CartCalculator;
use Contena\Core\Checkout\Cart\CartRuleLoader;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Contena\Core\Checkout\Cart\LineItem\CartDataCollection;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\BlogLineItemFactory;
use Contena\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Content\Blog\Aggregate\BlogFeatureSet\BlogFeatureSetDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Cart\BlogCartProcessor;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\CustomField\CustomFieldTypes;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class BlogCartProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    final public const TEST_LANGUAGE_LOCALE_CODE = 'sw-AG';
    final public const TEST_LANGUAGE_ID = 'cc72c24b82684d72a4ce91054da264bf';
    final public const TEST_LOCALE_ID = 'cf735c44dc7b4428bb3870fe4ffea2df';
    final public const CUSTOM_FIELD_ID = '24c8b3e8cacc4bf2a743b8c5a7522a33';
    final public const PURCHASE_STEP_QUANTITY_ERROR_KEY = 'purchase-steps-quantity';
    final public const MIN_ORDER_QUANTITY_ERROR_KEY = 'min-order-quantity';
    final public const BLOG_STOCK_REACHED_ERROR_KEY = 'blog-stock-reached';

    private IdsCollection $ids;



    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->calculator = static::getContainer()->get(QuantityPriceCalculator::class);
    }

    public function testDeliveryInformation(): void
    {
        $this->createBlog();

        $cart = $this->getBlogCart();
        $lineItem = $cart->get($this->ids->get('blog'));

        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertInstanceOf(DeliveryInformation::class, $lineItem->getDeliveryInformation());

        $info = $lineItem->getDeliveryInformation();
        static::assertSame(100.0, $info->getWeight());
        static::assertSame(101.0, $info->getHeight());
        static::assertSame(102.0, $info->getWidth());
        static::assertSame(103.0, $info->getLength());
    }

    public function testDeliveryInformationWithEmptyWeight(): void
    {
        $this->createBlog(['weight' => null]);

        $cart = $this->getBlogCart();
        $lineItem = $cart->get($this->ids->get('blog'));

        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertInstanceOf(DeliveryInformation::class, $lineItem->getDeliveryInformation());

        $info = $lineItem->getDeliveryInformation();

        static::assertNull($info->getWeight());
    }

    public function testNotCompletedLogic(): void
    {
        $context = $this->getContext();

        $this->createBlog();
        $cart = $this->getBlogCart();

        $lineItem = $cart->get($this->ids->get('blog'));
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame('test', $lineItem->getLabel());

        $update = ['id' => $this->ids->get('blog'), 'name' => 'update'];
        static::getContainer()->get('blog.repository')->upsert([$update], $context->getContext());

        $cart = $this->cartService->getCart($context->getToken(), $this->getContext(), false);

        $lineItem = $cart->get($this->ids->get('blog'));
        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertSame('update', $lineItem->getLabel());
    }

    public function testReferencePriceWithZeroPurchaseUnit(): void
    {
        $this->createBlog([
            'purchaseUnit' => 0.0,
            'referenceUnit' => 1.0,
            'unit' => [
                'shortCode' => 't',
                'name' => 'test',
            ],
        ]);

        $cart = $this->getBlogCart();
        $lineItem = $cart->get($this->ids->get('blog'));

        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertInstanceOf(QuantityPriceDefinition::class, $lineItem->getPriceDefinition());

        /** @var QuantityPriceDefinition $priceDefinition */
        $priceDefinition = $lineItem->getPriceDefinition();
        static::assertNull($priceDefinition->getReferencePriceDefinition());

        static::assertInstanceOf(CalculatedPrice::class, $lineItem->getPrice());
        static::assertNull($lineItem->getPrice()->getReferencePrice());
    }

    #[DataProvider('advancedPricingProvider')]
    public function testAdvancedPricing(bool $valid, float $price): void
    {
        $ids = new IdsCollection();

        $blog = (new BlogBuilder($ids, 'test'))
            ->price(100)
            ->prices('rule-1', 200, 'default', null, 1, $valid)
            ->visibility()
            ->build();

        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $result = static::getContainer()->get(CartRuleLoader::class)
            ->loadByToken($context, Uuid::randomHex());

        $cart = $result->getCart();

        static::assertSame($valid, \in_array($ids->get('rule-1'), $context->getRuleIds(), true));

        $lineItem = static::getContainer()->get(BlogLineItemFactory::class)
            ->create(['id' => $ids->get('test'), 'referencedId' => $ids->get('test')], $context);

        $cart = static::getContainer()->get(CartService::class)
            ->add($cart, [$lineItem], $context);

        static::assertCount(1, $cart->getLineItems());

        $lineItem = $cart->getLineItems()->first();
        static::assertNotNull($lineItem);
        static::assertSame('blog', $lineItem->getType());
        static::assertSame($ids->get('test'), $lineItem->getReferencedId());

        /** @var CalculatedPrice $calcPrice */
        $calcPrice = $lineItem->getPrice();
        static::assertSame($price, $calcPrice->getTotalPrice());
    }

    /**
     * @return \Traversable<string, array{0: bool, 1: int}>
     */
    public static function advancedPricingProvider(): \Traversable
    {
        yield 'Test not matching rule' => [false, 100];

        yield 'Test matching rule' => [true, 200];
    }

    public function testOverwriteLabelNoPermission(): void
    {
        $this->createBlog();
        $service = static::getContainer()->get(CartService::class);
        $token = $this->ids->create('token');
        $context = static::getContainer()->get(ChannelContextService::class)
            ->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $token));

        $blog = static::getContainer()->get(BlogLineItemFactory::class)
            ->create(['id' => $this->ids->get('blog'), 'referencedId' => $this->ids->get('blog')], $context);

        $blog->setLabel('My special blog');

        $cart = $service->getCart($token, $context);
        $service->add($cart, $blog, $context);

        $actualBlog = $cart->get($blog->getId());
        static::assertInstanceOf(LineItem::class, $actualBlog);
        static::assertSame('test', $actualBlog->getLabel());
    }

    public function testOverwriteLabelWithPermission(): void
    {
        $this->createBlog();
        $service = static::getContainer()->get(CartService::class);
        $token = $this->ids->create('token');
        $options = [
            ChannelContextService::PERMISSIONS => [BlogCartProcessor::ALLOW_BLOG_LABEL_OVERWRITES => true],
        ];

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create($token, TestDefaults::CHANNEL, $options);

        $blog = static::getContainer()->get(BlogLineItemFactory::class)
            ->create(['id' => $this->ids->get('blog'), 'referencedId' => $this->ids->get('blog')], $context);

        $blog->setLabel('My special blog');

        $cart = $service->getCart($token, $context);
        $service->add($cart, $blog, $context);

        $actualBlog = $cart->get($blog->getId());
        static::assertInstanceOf(LineItem::class, $actualBlog);
        static::assertSame('My special blog', $actualBlog->getLabel());
    }

    public function testOverwriteLabelWithPermissionNoLabel(): void
    {
        $this->createBlog();
        $service = static::getContainer()->get(CartService::class);
        $token = $this->ids->create('token');
        $options = [
            ChannelContextService::PERMISSIONS => [BlogCartProcessor::ALLOW_BLOG_LABEL_OVERWRITES => true],
        ];

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create($token, TestDefaults::CHANNEL, $options);

        $blog = static::getContainer()->get(BlogLineItemFactory::class)
            ->create(['id' => $this->ids->get('blog'), 'referencedId' => $this->ids->get('blog')], $context);

        $blog->setLabel(null);

        $cart = $service->getCart($token, $context);
        $service->add($cart, $blog, $context);

        $actualBlog = $cart->get($blog->getId());
        static::assertInstanceOf(LineItem::class, $actualBlog);
        static::assertSame('test', $actualBlog->getLabel());
    }

    public function testLineItemPropertiesPurchasePrice(): void
    {
        $this->createBlog();

        $token = $this->ids->create('token');
        $channelContextService = static::getContainer()->get(ChannelContextService::class);
        $context = $channelContextService->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $token, null, Defaults::CURRENCY));
        $cartService = static::getContainer()->get(CartService::class);
        $cart = $cartService->getCart($token, $context);
        $blog = static::getContainer()->get(BlogLineItemFactory::class)
            ->create(['id' => $this->ids->get('blog'), 'referencedId' => $this->ids->get('blog')], $context);
        $cartService->add($cart, $blog, $context);

        $blogCartProcessor = static::getContainer()->get(BlogCartProcessor::class);
        $blogCartProcessor->collect(
            new CartDataCollection(),
            $cart,
            $context,
            new CartBehavior()
        );

        $lineItem = $cart->get($blog->getId());

        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertArrayHasKey('purchasePrices', $lineItem->getPayload());

        $purchasePrices = json_decode((string) $lineItem->getPayloadValue('purchasePrices'), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Defaults::CURRENCY, $purchasePrices['currencyId']);
        static::assertSame(7.5, $purchasePrices['gross']);
        static::assertSame(5, $purchasePrices['net']);
        static::assertFalse($purchasePrices['linked']);
    }

    public function testPayloadContainsFeatures(): void
    {
        $this->createBlog();

        $cart = $this->getBlogCart();
        $lineItem = $cart->get($this->ids->get('blog'));

        static::assertInstanceOf(LineItem::class, $lineItem);
        static::assertArrayHasKey('features', $lineItem->getPayload());
    }

    /**
     * @param array{type: string, id: string|null, name: string|null, position: int} $testedFeature
     * @param array<string, mixed> $blogData
     * @param array{type: string, value: mixed, label: string} $expectedFeature
     * @param array<string, mixed> $customFieldData
     */
    #[DataProvider('blogFeatureProvider')]
    public function testBlogFeaturesContainCorrectInformation(array $testedFeature, array $blogData, array $expectedFeature, array $customFieldData = []): void
    {
        $this->createLanguage(self::TEST_LANGUAGE_ID);

        if ($testedFeature['type'] === BlogFeatureSetDefinition::TYPE_BLOG_CUSTOM_FIELD) {
            $this->createCustomField($customFieldData);
        }

        $this->createBlog([...[
            'featureSet' => $this->createFeatureSet([$testedFeature]),
        ], ...$blogData]);

        $cart = $this->getBlogCart();
        $lineItem = $cart->get($this->ids->get('blog'));

        static::assertInstanceOf(LineItem::class, $lineItem);
        $features = $lineItem->getPayload()['features'];
        $feature = array_pop($features);

        static::assertArrayHasKey('label', $feature);
        static::assertArrayHasKey('value', $feature);
        static::assertArrayHasKey('type', $feature);

        if ($testedFeature['type'] === BlogFeatureSetDefinition::TYPE_BLOG_REFERENCE_PRICE) {
            unset($expectedFeature['value']['price'], $feature['value']['price']);
        }

        static::assertSame($expectedFeature, $feature);
    }

    /**
     * @return iterable<string, array{
     *     array{type: string, id: string|null, name: string|null, position: int},
     *     array<string, mixed>,
     *     array{type: string, value: mixed, label: string},
     *     3?: array<string, mixed>
     * }>
     */
    public static function blogFeatureProvider(): iterable
    {
        yield 'translated blog description is exposed as an attribute feature' => [
            [
                'type' => BlogFeatureSetDefinition::TYPE_BLOG_ATTRIBUTE,
                'id' => null,
                'name' => 'description',
                'position' => 1,
            ],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Default',
                        'description' => 'Default',
                    ],
                    self::TEST_LANGUAGE_ID => [
                        'description' => 'Lorem ipsum dolor sit amet.',
                    ],
                ],
            ],
            [
                'label' => 'description',
                'value' => 'Lorem ipsum dolor sit amet.',
                'type' => BlogFeatureSetDefinition::TYPE_BLOG_ATTRIBUTE,
            ],
        ];
        yield 'manufacturer number is exposed as an attribute feature' => [
            [
                'type' => BlogFeatureSetDefinition::TYPE_BLOG_ATTRIBUTE,
                'id' => null,
                'name' => 'manufacturerNumber',
                'position' => 1,
            ],
            [
                'manufacturerNumber' => '22ee3d8063da',
            ],
            [
                'label' => 'manufacturerNumber',
                'value' => '22ee3d8063da',
                'type' => BlogFeatureSetDefinition::TYPE_BLOG_ATTRIBUTE,
            ],
        ];
        yield 'translated property options are exposed as a property feature' => [
            [
                'type' => BlogFeatureSetDefinition::TYPE_BLOG_PROPERTY,
                'id' => '7c8e7851ff88447ba254d3c2a7c45101',
                'name' => null,
                'position' => 2,
            ],
            [
                'properties' => [
                    [
                        'id' => 'bf821e9e206848579049bc1694c5c3e7',
                    ],
                    [
                        'id' => '0cfabe6eab0440b0974b7b7164556612',
                    ],
                ],
                'options' => [
                    [
                        'id' => 'bf821e9e206848579049bc1694c5c3e7',
                        'position' => 99,
                        'colorHexCode' => '#189eff',
                        'group' => [
                            'id' => '7c8e7851ff88447ba254d3c2a7c45101',
                            'position' => 1,
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => [
                                    'name' => 'Default',
                                    'description' => 'Default',
                                    'displayType' => 'Default',
                                    'sortingType' => 'Default',
                                ],
                                self::TEST_LANGUAGE_ID => [
                                    'name' => 'swag_color',
                                    'description' => 'Lorem ipsum',
                                    'displayType' => 'color',
                                    'sortingType' => 'alphanumeric',
                                ],
                            ],
                        ],
                        'translations' => [
                            Defaults::LANGUAGE_SYSTEM => [
                                'name' => 'Default',
                            ],
                            self::TEST_LANGUAGE_ID => [
                                'name' => 'Blue',
                            ],
                        ],
                    ],
                    [
                        'id' => '0cfabe6eab0440b0974b7b7164556612',
                        'position' => 98,
                        'colorHexCode' => '#ff0000',
                        'groupId' => '7c8e7851ff88447ba254d3c2a7c45101',
                        'translations' => [
                            Defaults::LANGUAGE_SYSTEM => [
                                'name' => 'Default',
                            ],
                            self::TEST_LANGUAGE_ID => [
                                'name' => 'Red',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'label' => 'swag_color',
                'value' => [
                    '0cfabe6eab0440b0974b7b7164556612' => [
                        'id' => '0cfabe6eab0440b0974b7b7164556612',
                        'name' => 'Red',
                        'mediaId' => null,
                        'colorHexCode' => '#ff0000',
                    ],
                    'bf821e9e206848579049bc1694c5c3e7' => [
                        'id' => 'bf821e9e206848579049bc1694c5c3e7',
                        'name' => 'Blue',
                        'mediaId' => null,
                        'colorHexCode' => '#189eff',
                    ],
                ],
                'type' => BlogFeatureSetDefinition::TYPE_BLOG_PROPERTY,
            ],
        ];
        yield 'translated custom field is exposed as a custom field feature' => [
            [
                'type' => BlogFeatureSetDefinition::TYPE_BLOG_CUSTOM_FIELD,
                'id' => null,
                'name' => 'lorem_ipsum',
                'position' => 3,
            ],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Default',
                        'customFields' => [
                            'lorem_ipsum' => 'Default',
                        ],
                    ],
                    self::TEST_LANGUAGE_ID => [
                        'customFields' => [
                            'lorem_ipsum' => 'Dolor sit amet.',
                        ],
                    ],
                ],
            ],
            [
                'label' => 'lorem_ipsum',
                'value' => [
                    'id' => self::CUSTOM_FIELD_ID,
                    'type' => CustomFieldTypes::TEXT,
                    'content' => 'Dolor sit amet.',
                ],
                'type' => BlogFeatureSetDefinition::TYPE_BLOG_CUSTOM_FIELD,
            ],
        ];
        yield 'custom field of the system language is exposed as a custom field feature' => [
            [
                'type' => BlogFeatureSetDefinition::TYPE_BLOG_CUSTOM_FIELD,
                'id' => null,
                'name' => 'lorem_ipsum',
                'position' => 3,
            ],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Default',
                        'customFields' => [
                            'lorem_ipsum' => 'Default',
                        ],
                    ],
                    self::TEST_LANGUAGE_ID => [
                        'name' => 'Dolor sit amet.',
                    ],
                ],
            ],
            [
                'label' => 'lorem_ipsum',
                'value' => [
                    'id' => self::CUSTOM_FIELD_ID,
                    'type' => CustomFieldTypes::TEXT,
                    'content' => 'Default',
                ],
                'type' => BlogFeatureSetDefinition::TYPE_BLOG_CUSTOM_FIELD,
            ],
        ];
        yield 'select custom field exposes the labels of the selected options' => [
            [
                'type' => BlogFeatureSetDefinition::TYPE_BLOG_CUSTOM_FIELD,
                'id' => null,
                'name' => 'lorem_ipsum',
                'position' => 3,
            ],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Default',
                        'customFields' => [
                            'lorem_ipsum' => ['oak', 'pine'],
                        ],
                    ],
                ],
            ],
            [
                'label' => 'lorem_ipsum',
                'value' => [
                    'id' => self::CUSTOM_FIELD_ID,
                    'type' => CustomFieldTypes::SELECT,
                    'content' => ['oak', 'pine'],
                    'display' => ['Oak', 'Pine'],
                ],
                'type' => BlogFeatureSetDefinition::TYPE_BLOG_CUSTOM_FIELD,
            ],
            [
                'type' => CustomFieldTypes::SELECT,
                'config' => [
                    'componentName' => 'sw-multi-select',
                    'customFieldType' => CustomFieldTypes::SELECT,
                    'label' => [
                        'en-GB' => 'lorem_ipsum',
                    ],
                    'options' => [
                        ['value' => 'oak', 'label' => ['en-GB' => 'Oak']],
                        ['value' => 'pine', 'label' => ['en-GB' => 'Pine']],
                    ],
                ],
            ],
        ];
        yield 'translated pack unit is exposed as a reference price feature' => [
            [
                'type' => BlogFeatureSetDefinition::TYPE_BLOG_REFERENCE_PRICE,
                'id' => null,
                'name' => null,
                'position' => 0,
            ],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Default',
                        'packUnit' => 'Default',
                        'packUnitPlural' => 'Default',
                    ],
                    self::TEST_LANGUAGE_ID => [
                        'packUnit' => 'Can',
                        'packUnitPlural' => 'Cans',
                    ],
                ],
                'unit' => [
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => [
                            'shortCode' => 'Default',
                            'name' => 'Default',
                        ],
                        self::TEST_LANGUAGE_ID => [
                            'shortCode' => 'l',
                            'name' => 'litres',
                        ],
                    ],
                ],
                'purchaseUnit' => 2,
                'referenceUnit' => 0.33,
            ],
            [
                'label' => BlogFeatureSetDefinition::TYPE_BLOG_REFERENCE_PRICE,
                'value' => [
                    'purchaseUnit' => 2.0,
                    'referenceUnit' => 0.33,
                    'unitName' => 'litres',
                ],
                'type' => BlogFeatureSetDefinition::TYPE_BLOG_REFERENCE_PRICE,
            ],
        ];
    }

    public function testProcessCartShouldSkipBlogStockValidation(): void
    {
        $this->createBlog();
        $service = static::getContainer()->get(CartService::class);
        $token = $this->ids->create('token');
        $options = [
            ChannelContextService::PERMISSIONS => [BlogCartProcessor::SKIP_BLOG_STOCK_VALIDATION => true],
        ];

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create($token, TestDefaults::CHANNEL, $options);

        $blog = static::getContainer()->get(BlogLineItemFactory::class)
            ->create(['id' => $this->ids->get('blog'), 'referencedId' => $this->ids->get('blog')], $context);

        $blog->setLabel('My special blog');

        $cart = $service->getCart($token, $context);
        $service->add($cart, $blog, $context);

        $actualBlog = $cart->get($blog->getId());

        static::assertInstanceOf(LineItem::class, $blog);
        static::assertInstanceOf(LineItem::class, $actualBlog);
        static::assertNotNull($blog->getPriceDefinition());
        static::assertInstanceOf(QuantityPriceDefinition::class, $blog->getPriceDefinition());
        static::assertSame($blog->getQuantity(), $actualBlog->getQuantity());
        static::assertEquals($blog->getPrice(), $this->calculator->calculate($blog->getPriceDefinition(), $context));
        static::assertEquals($blog, $actualBlog);
    }

    #[DataProvider('blogDeliverabilityProvider')]
    public function testProcessCartShouldReturnFixedQuantity(int $minPurchase, int $purchaseSteps, int $maxPurchase, int $quantity, int $quantityExpected, ?string $errorKey): void
    {
        $additionalData = [
            'maxPurchase' => $maxPurchase,
            'minPurchase' => $minPurchase,
            'purchaseSteps' => $purchaseSteps,
        ];
        $this->createBlog($additionalData);
        $service = static::getContainer()->get(CartService::class);
        $token = $this->ids->create('token');
        $options = [
            ChannelContextService::PERMISSIONS => [BlogCartProcessor::ALLOW_BLOG_PRICE_OVERWRITES => true],
        ];

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create($token, TestDefaults::CHANNEL, $options);

        $config = [
            'id' => $this->ids->get('blog'),
            'referencedId' => $this->ids->get('blog'),
            'quantity' => $quantity,
        ];
        $blog = static::getContainer()->get(BlogLineItemFactory::class)
            ->create($config, $context);

        $blog->setLabel('My special blog');

        $cart = $service->getCart($token, $context);
        $service->add($cart, $blog, $context);

        $actualBlog = $cart->get($blog->getId());
        static::assertInstanceOf(LineItem::class, $actualBlog);
        static::assertSame($quantityExpected, $actualBlog->getQuantity());
        if ($errorKey !== null) {
            $error = $service->getCart($token, $context)->getErrors()->first();
            static::assertNotNull($error);
            static::assertSame($errorKey, $error->getMessageKey());
        }
    }

    /**
     * @return iterable<string, array{0: int, 1: int, 2: int, 3: int, 4: int, 5: string}>
     */
    public static function blogDeliverabilityProvider(): iterable
    {
        yield 'quantity 5 is reduced to purchase step 4' => [2, 2, 20, 5, 4, self::PURCHASE_STEP_QUANTITY_ERROR_KEY];
        yield 'quantity 10 is reduced to purchase step 9' => [1, 2, 20, 10, 9, self::PURCHASE_STEP_QUANTITY_ERROR_KEY];
        yield 'quantity 6 is reduced to minimum purchase 5' => [5, 5, 20, 6, 5, self::PURCHASE_STEP_QUANTITY_ERROR_KEY];
        yield 'quantity above stock is capped at max purchase' => [2, 2, 20, 22, 20, self::BLOG_STOCK_REACHED_ERROR_KEY];
        yield 'quantity below minimum is raised to min purchase' => [2, 2, 20, 1, 2, self::MIN_ORDER_QUANTITY_ERROR_KEY];
        yield 'quantity 2 is reduced to purchase step 1' => [1, 3, 5, 2, 1, self::PURCHASE_STEP_QUANTITY_ERROR_KEY];
        yield 'quantity 11 is reduced to purchase step 10' => [10, 3, 13, 11, 10, self::PURCHASE_STEP_QUANTITY_ERROR_KEY];
        yield 'quantity below high minimum is raised to min purchase' => [10, 2, 20, 2, 10, self::MIN_ORDER_QUANTITY_ERROR_KEY];
    }

    public function testProcessCartShouldSetQuantityOfPriceDefinitionWhenAddingASimilarBlog(): void
    {
        $this->createBlog();
        $token = $this->ids->create('token');
        $options = [
            ChannelContextService::PERMISSIONS => [
                BlogCartProcessor::SKIP_BLOG_STOCK_VALIDATION => false,
                BlogCartProcessor::ALLOW_BLOG_PRICE_OVERWRITES => true,
            ],
        ];

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create($token, TestDefaults::CHANNEL, $options);

        $definition = new QuantityPriceDefinition(10, new TaxRuleCollection(), 1);

        $blog = static::getContainer()->get(BlogLineItemFactory::class)
            ->create(['id' => $this->ids->get('blog'), 'referencedId' => $this->ids->get('blog')], $context);
        $blog->setPriceDefinition($definition);
        $blog->setLabel('My test blog');
        $blog->setQuantity(5);

        $cart = $this->cartService->getCart($token, $context);
        $this->cartService->add($cart, $blog, $context);
        $this->cartService->add($cart, $blog, $context);

        $actualBlog = $cart->get($blog->getId());

        static::assertInstanceOf(LineItem::class, $blog);
        static::assertInstanceOf(LineItem::class, $actualBlog);
        static::assertNotNull($blog->getPriceDefinition());
        static::assertInstanceOf(QuantityPriceDefinition::class, $blog->getPriceDefinition());
        static::assertSame($blog->getQuantity(), $actualBlog->getQuantity());
        static::assertEquals($blog->getPrice(), $this->calculator->calculate($blog->getPriceDefinition(), $context));
        static::assertEquals($blog, $actualBlog);
    }

    public function testProcessCartShouldReCalculateThePriceWhenAddABlogAndHasNoCustomPrice(): void
    {
        $this->createBlog();
        $token = $this->ids->create('token');
        $options = [
            ChannelContextService::PERMISSIONS => [
                BlogCartProcessor::SKIP_BLOG_STOCK_VALIDATION => false,
                BlogCartProcessor::ALLOW_BLOG_PRICE_OVERWRITES => true,
            ],
        ];

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create($token, TestDefaults::CHANNEL, $options);

        $blog = static::getContainer()->get(BlogLineItemFactory::class)
            ->create(['id' => $this->ids->get('blog'), 'referencedId' => $this->ids->get('blog')], $context);
        $blog->setLabel('My test blog');

        $cart = $this->cartService->getCart($token, $context);
        $this->cartService->add($cart, $blog, $context);

        $actualBlog = $cart->get($blog->getId());

        static::assertInstanceOf(LineItem::class, $blog);
        static::assertInstanceOf(LineItem::class, $actualBlog);
        static::assertNotNull($blog->getPriceDefinition());
        static::assertInstanceOf(QuantityPriceDefinition::class, $blog->getPriceDefinition());
        static::assertSame($blog->getQuantity(), $actualBlog->getQuantity());
        static::assertEquals($blog->getPrice(), $this->calculator->calculate($blog->getPriceDefinition(), $context));
        static::assertEquals($blog, $actualBlog);
    }

    public function testProcessCartWithNulledFreeShipping(): void
    {
        $this->createBlog([
            'shippingFree' => null,
        ]);
        $token = $this->ids->create('token');
        $options = [
            ChannelContextService::PERMISSIONS => [
                BlogCartProcessor::SKIP_BLOG_STOCK_VALIDATION => false,
                BlogCartProcessor::ALLOW_BLOG_PRICE_OVERWRITES => true,
            ],
        ];

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create($token, TestDefaults::CHANNEL, $options);

        $blog = static::getContainer()->get(BlogLineItemFactory::class)
            ->create(['id' => $this->ids->get('blog'), 'referencedId' => $this->ids->get('blog')], $context);
        $blog->setLabel('My test blog');

        $cart = $this->cartService->getCart($token, $context);
        $this->cartService->add($cart, $blog, $context);

        $actualBlog = $cart->get($blog->getId());

        static::assertInstanceOf(LineItem::class, $actualBlog);
        static::assertInstanceOf(DeliveryInformation::class, $actualBlog->getDeliveryInformation());
        static::assertFalse($actualBlog->getDeliveryInformation()->getFreeDelivery());
    }

    public function testInactiveBlogCartRemoval(): void
    {
        $context = $this->getContext();
        $this->createBlog();
        $this->getBlogCart();

        $update = ['id' => $this->ids->get('blog'), 'active' => false];
        static::getContainer()->get('blog.repository')->upsert([$update], $context->getContext());

        $cart = $this->cartService->getCart($context->getToken(), $this->getContext(), false);

        $lineItem = $cart->get($this->ids->get('blog'));
        static::assertNull($lineItem);
    }

    public function testDeletedBlogCartRemoval(): void
    {
        $context = $this->getContext();
        $this->createBlog();
        $this->getBlogCart();

        static::getContainer()->get('blog.repository')->delete([['id' => $this->ids->get('blog')]], $context->getContext());

        $cart = $this->cartService->getCart($context->getToken(), $this->getContext(), false);

        $lineItem = $cart->get($this->ids->get('blog'));
        static::assertNull($lineItem);
    }

    public function testTaxIsRecalculatedOnCountryChange(): void
    {
        $deCountryId = static::getContainer()->get('country.repository')->searchIds((new Criteria())->addFilter(new EqualsFilter('iso', 'DE')), Context::createDefaultContext())->firstId();
        $userId = $this->createUser($deCountryId ?? $this->getValidCountryId());
        $parameters = new ChannelContextServiceParameters(TestDefaults::CHANNEL, $this->ids->create('token'), userId: $userId);
        $context = static::getContainer()->get(ChannelContextService::class)->get($parameters);

        $this->createBlog([
            'taxId' => static::getContainer()->get(SystemConfigService::class)->get('core.tax.defaultTaxRate'),
        ]);

        $cart = $this->cartService->add(
            $this->cartService->getCart($context->getToken(), $context),
            static::getContainer()->get(BlogLineItemFactory::class)->create(['id' => $this->ids->get('blog')], $context),
            $context
        );

        $lineItem = $cart->get($this->ids->get('blog'));
        static::assertSame(19.0, $lineItem?->getPrice()?->getTaxRules()?->first()?->getTaxRate());

        $upsert = [
            'id' => $context->getUser()?->getDefaultShippingAddress()?->getId(),
            'countryId' => static::getContainer()->get('country.repository')->searchIds((new Criteria())->addFilter(new EqualsFilter('iso', 'NL')), $context->getContext())->firstId(),
        ];
        static::getContainer()->get('user_address.repository')->upsert([$upsert], $context->getContext());

        $context = static::getContainer()->get(ChannelContextService::class)->get($parameters);
        $cart = $this->cartService->getCart($context->getToken(), $context, false);
        $lineItem = $cart->get($this->ids->get('blog'));
        static::assertSame(21.0, $lineItem?->getPrice()?->getTaxRules()?->first()?->getTaxRate());
    }

    public function testTaxFreeIsConsideredOnMultipleCalculations(): void
    {
        static::getContainer()->get('currency.repository')->upsert([['id' => Defaults::CURRENCY, 'taxFreeFrom' => 1]], Context::createDefaultContext());
        $context = $this->getContext();
        $this->createBlog();
        $cart = $this->getBlogCart();

        static::getContainer()->get(CartCalculator::class)->calculate($cart, $context);
        static::assertSame(10.0, $cart->getLineItems()->first()?->getPrice()?->getTotalPrice());
    }

    public function testProducePriceChangeAfterContextRuleChange(): void
    {
        $countryIds = $this->getCountryIds();

        static::assertIsString($countryIds[0]);

        $userId = $this->createUser($countryIds[0]);

        $this->createBlog([
            'prices' => [
                [
                    'quantityStart' => 1,
                    'rule' => [
                        'id' => $this->ids->create('rule'),
                        'name' => 'Test rule',
                        'priority' => 1,
                        'conditions' => [
                            [
                                'type' => 'userShippingCountry',
                                'value' => [
                                    'operator' => '=',
                                    'countryIds' => [$countryIds[1]],
                                ],
                            ],
                        ],
                    ],
                    'price' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'gross' => 50,
                            'net' => 9, 'linked' => false,
                            'listPrice' => ['gross' => 60, 'net' => 60, 'linked' => false],
                        ],
                    ],
                ],
            ],
        ]);
        $token = $this->ids->create('token');

        $context = static::getContainer()->get(ChannelContextFactory::class)->create($token, TestDefaults::CHANNEL, [ChannelContextService::CUSTOMER_ID => $userId]);

        static::assertNotNull($context->getUser());

        $blog = static::getContainer()->get(BlogLineItemFactory::class)
            ->create(['id' => $this->ids->get('blog'), 'referencedId' => $this->ids->get('blog')], $context);

        $cart = $this->cartService->getCart($token, $context);
        $this->cartService->add($cart, $blog, $context);

        $actualBlog = $cart->get($blog->getId());

        static::assertSame(15.0, $actualBlog?->getPrice()?->getTotalPrice());

        static::getContainer()->get('user_address.repository')->update([
            [
                'id' => $context->getUser()->getDefaultBillingAddressId(),
                'countryId' => $countryIds[1],
            ],
        ], Context::createDefaultContext());

        $context = static::getContainer()->get(ChannelContextFactory::class)->create($token, TestDefaults::CHANNEL, [ChannelContextService::CUSTOMER_ID => $userId]);

        $cart = $this->cartService->getCart($token, $context, false);

        $actualBlog = $cart->get($blog->getId());

        static::assertSame(50.0, $actualBlog?->getPrice()?->getTotalPrice());
    }

    /**
     * @return list<string>
     */
    private function getCountryIds(): array
    {
        /** @var EntityRepository<CountryCollection> $repository */
        $repository = static::getContainer()->get('country.repository');

        $criteria = (new Criteria())->setLimit(2)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('shippingAvailable', true))
            ->addSorting(new FieldSorting('iso'));

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds();
    }

    private function createUser(string $countryId): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'userNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'channelId' => TestDefaults::CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'userId' => $userId,
                    'countryId' => $countryId,
                    'salutationId' => $this->getValidSalutationId(),
                    'name' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()->get('user.repository')->upsert([$user], Context::createDefaultContext());

        return $userId;
    }


    private function getContext(): ChannelContext
    {
        $token = $this->ids->create('token');

        return static::getContainer()->get(ChannelContextService::class)
            ->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $token));
    }

    /**
     * @param array<string, mixed> $additionalData
     */
    private function createBlog(array $additionalData = []): void
    {
        $data = [
            'id' => $this->ids->create('blog'),
            'name' => 'test',
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'purchasePrices' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 7.5, 'net' => 5, 'linked' => false],
                ['currencyId' => Uuid::randomHex(), 'gross' => 150, 'net' => 100, 'linked' => false],
            ],
            'active' => true,
            'taxId' => $this->getValidTaxId(),
            'weight' => 100,
            'height' => 101,
            'width' => 102,
            'length' => 103,
            'visibilities' => [
                ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'test',
                ],
            ],
        ];

        $data = array_merge($data, $additionalData);

        static::getContainer()->get('blog.repository')
            ->create([$data], Context::createDefaultContext());
    }

    /**
     * @param array<string, mixed>|null $additionalData
     */
    private function createCustomField(?array $additionalData = []): void
    {
        if ($additionalData === null) {
            $additionalData = [];
        }

        $data = array_merge([
            'id' => self::CUSTOM_FIELD_ID,
            'name' => 'lorem_ipsum',
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'componentName' => 'sw-field',
                'customFieldPosition' => 1,
                'customFieldType' => CustomFieldTypes::TEXT,
                'type' => CustomFieldTypes::TEXT,
                'label' => [
                    'en-GB' => 'lorem_ipsum',
                    'de-DE' => 'lorem_ipsum',
                ],
            ],
        ], $additionalData);

        static::getContainer()->get('custom_field.repository')
            ->create([$data], Context::createDefaultContext());
    }

    /**
     * @param list<array{type: string, id: string|null, name: string|null, position: int}> $features
     *
     * @return array<string, mixed>
     */
    private function createFeatureSet(array $features): array
    {
        return [
            'id' => $this->ids->create('blog-feature-set'),
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'Test feature set',
                    'description' => 'Lorem ipsum dolor sit amet',
                ],
            ],
            'features' => $features,
        ];
    }

    private function createLanguage(string $id, ?string $parentId = Defaults::LANGUAGE_SYSTEM): void
    {
        $languageRepository = static::getContainer()->get('language.repository');

        $languageRepository->create(
            [
                [
                    'id' => $id,
                    'name' => \sprintf('name-%s', $id),
                    'localeId' => $this->getLocaleIdOfSystemLanguage(),
                    'parentId' => $parentId,
                    'active' => true,
                    'translationCode' => [
                        'id' => self::TEST_LOCALE_ID,
                        'code' => self::TEST_LANGUAGE_LOCALE_CODE,
                        'name' => 'Test locale',
                        'territory' => 'test',
                    ],
                    'channels' => [
                        ['id' => TestDefaults::CHANNEL],
                    ],
                    'channelDefaultAssignments' => [
                        ['id' => TestDefaults::CHANNEL],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );
    }
}
