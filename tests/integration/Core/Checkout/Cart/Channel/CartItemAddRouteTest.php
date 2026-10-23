<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Cart\BlogCartProcessor;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
#[Group('cart')]
class CartItemAddRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));
        $this->blogRepository = static::getContainer()->get('blog.repository');

        $this->createTestData();
    }

    public function testFillCartBlogs(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'label' => 'foo',
                            'type' => 'blog',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
        static::assertSame('Test', $response['lineItems'][0]['label']);
    }

    public function testFillCartWithoutRequestContextTokenReturnsReusableContextToken(): void
    {
        $this->browser->setServerParameters([
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_SW_ACCESS_KEY' => $this->browser->getServerParameter('HTTP_SW_ACCESS_KEY'),
            'test-sales-channel-id' => $this->browser->getServerParameter('test-sales-channel-id'),
        ]);

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'label' => 'foo',
                            'type' => 'blog',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ]
            );

        $response = $this->browser->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
        $this->browser->request('GET', '/store-api/checkout/cart');

        $content = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $content['apiAlias']);
        static::assertCount(1, $content['lineItems']);
        static::assertSame($this->ids->get('p1'), $content['lineItems'][0]['referencedId']);
    }

    public function testAddExistingLineItemChangesQuantityAndMarksModified(): void
    {
        $this->browser
            ->jsonRequest(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p2'),
                            'label' => 'foo',
                            'type' => 'blog',
                            'referencedId' => $this->ids->get('p2'),
                            'quantity' => 9,
                        ],
                    ],
                ]
            );
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->jsonRequest(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p2'),
                            'label' => 'foo',
                            'type' => 'blog',
                            'referencedId' => $this->ids->get('p2'),
                            'quantity' => 1,
                        ],
                    ],
                ]
            );
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(50, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
        static::assertSame('Test', $response['lineItems'][0]['label']);
    }

    public function testFillCartOutOfStock(): void
    {
        $this->browser->setServerParameter('HTTP_sw-include-seo-urls', '1');
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p3'),
                            'label' => 'foo',
                            'type' => 'blog',
                            'referencedId' => $this->ids->get('p3'),
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(0, $response['price']['totalPrice']);
        static::assertCount(0, $response['lineItems']);
        static::assertCount(1, $response['errors']);
        static::assertSame('The blog Test is no longer available', array_column($response['errors'], 'message')[0]);
    }

    public function testFillCartMultipleBlogs(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'blog',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                        [
                            'id' => $this->ids->get('p2'),
                            'type' => 'blog',
                            'referencedId' => $this->ids->get('p2'),
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(20, $response['price']['totalPrice']);
        static::assertCount(2, $response['lineItems']);
        static::assertSame('Test', $response['lineItems'][0]['label']);
    }

    public function testAddCustomWithoutPermission(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'label' => 'Test',
                            'type' => 'credit',
                            'priceDefinition' => [
                                'price' => 100.0,
                                'type' => 'absolute',
                                'absolute' => 1.0,
                            ],
                        ],
                    ],
                ]
            );

        static::assertSame(403, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('CHECKOUT__INSUFFICIENT_PERMISSION', $response['errors'][0]['code']);
    }

    public function testAddCustomWithPermission(): void
    {
        $this->enableAdminAccess();
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'label' => 'Test',
                            'type' => 'credit',
                            'priceDefinition' => [
                                'price' => 100.0,
                                'type' => 'absolute',
                                'absolute' => 1.0,
                            ],
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(100, $response['price']['totalPrice']);
    }

    public function testCustomTaxIncludedInShippingCostTaxes(): void
    {
        $this->enableAdminAccess();

        $taxForBlogItem = 10;
        $taxForCustomItem = 15;

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'label' => 'blog item',
                            'type' => LineItem::BLOG_LINE_ITEM_TYPE,
                            'priceDefinition' => [
                                'price' => 100,
                                'taxRules' => [[
                                    'taxRate' => $taxForBlogItem,
                                    'percentage' => 100,
                                ]],
                                'type' => 'quantity',
                            ],
                            'referencedId' => $this->ids->get('p1'),
                        ],
                        [
                            'label' => 'custom item',
                            'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
                            'tax' => [
                                'id' => Uuid::randomHex(),
                                'taxRate' => $taxForCustomItem,
                                'name' => 'taxCustomItem',
                            ],
                            'priceDefinition' => [
                                'price' => 150,
                                'taxRules' => [[
                                    'taxRate' => $taxForCustomItem,
                                    'percentage' => 100,
                                ]],
                                'type' => 'quantity',
                            ],
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $cart = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('deliveries', $cart);
        static::assertCount(1, $deliveries = $cart['deliveries']);
        static::assertNotCount(0, $shippingCost = $deliveries[0]['shippingCosts']);
        static::assertCount(2, $shippingCostCalculatedTaxes = $shippingCost['calculatedTaxes']);

        // assert there is shipping cost calculated taxes for blog and custom items in cart
        $calculatedTaxForCustomItem = array_filter($shippingCostCalculatedTaxes, static fn ($tax) => $tax['taxRate'] === $taxForCustomItem);

        static::assertNotCount(0, $calculatedTaxForCustomItem);
        static::assertCount(1, $calculatedTaxForCustomItem);

        $calculatedTaxForBlogItem = array_filter($shippingCostCalculatedTaxes, static fn ($tax) => $tax['taxRate'] === $taxForBlogItem);

        static::assertNotCount(0, $calculatedTaxForBlogItem);
        static::assertCount(1, $calculatedTaxForBlogItem);
    }

    public function testAddPromotion(): void
    {
        $promotionId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);

        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), $this->ids->get('sales-channel'));

        $this->createTestFixtureBlog($blogId, 800, 19, static::getContainer(), $context);

        $this->createPromotion(
            $promotionId,
            $code,
            static::getContainer()->get('promotion.repository'),
            $context
        );

        $this->createTestFixtureDiscount($promotionId, PromotionDiscountEntity::TYPE_ABSOLUTE, PromotionDiscountEntity::SCOPE_CART, 10, null, static::getContainer(), $context);

        // Add blog
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $blogId,
                            'type' => 'blog',
                            'referencedId' => $blogId,
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        // Add code
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'type' => 'promotion',
                            'referencedId' => " \t{$code}\n",
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(790, $response['price']['totalPrice']);
        static::assertCount(2, $response['lineItems']);
        static::assertSame('Test', $response['lineItems'][0]['label']);

        $promotionLineItems = array_values(array_filter(
            $response['lineItems'],
            static fn (array $lineItem): bool => $lineItem['type'] === LineItem::PROMOTION_LINE_ITEM_TYPE
        ));

        static::assertCount(1, $promotionLineItems);
        static::assertSame($code, $promotionLineItems[0]['referencedId']);
    }

    private function createTestData(): void
    {
        $rule = Uuid::randomHex();
        static::getContainer()->get('rule.repository')->create([
            ['id' => $rule, 'name' => 'test', 'priority' => 1, 'conditions' => [['type' => (new AlwaysValidRule())->getName()]]],
        ], Context::createDefaultContext());

        $this->blogRepository->create([
            [
                'id' => $this->ids->create('p1'),
                'blogNumber' => $this->ids->get('p1'),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());

        $this->blogRepository->create([
            [
                'id' => $this->ids->create('p2'),
                'blogNumber' => $this->ids->get('p2'),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->get('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->get('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'quantityEnd' => 9,
                        'ruleId' => $rule,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
                    ],
                    [
                        'quantityStart' => 10,
                        'ruleId' => $rule,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 5, 'net' => 4, 'linked' => false]],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $this->blogRepository->create([
            [
                'id' => $this->ids->create('p3'),
                'blogNumber' => $this->ids->get('p3'),
                'stock' => 0,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->get('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->get('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'isCloseout' => true,
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());
    }

    private function enableAdminAccess(): void
    {
        $token = $this->browser->getServerParameter('HTTP_SW_CONTEXT_TOKEN');
        $payload = static::getContainer()->get(ChannelContextPersister::class)->load($token, $this->ids->get('sales-channel'));

        $payload[ChannelContextService::PERMISSIONS] = [BlogCartProcessor::ALLOW_BLOG_PRICE_OVERWRITES => true];

        static::getContainer()->get(ChannelContextPersister::class)->save($token, $payload, $this->ids->get('sales-channel'));
    }
}
