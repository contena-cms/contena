<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Rule\CartAmountRule;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Cart\BlogCartProcessor;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Container\AndRule;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
#[Group('cart')]
class CartItemUpdateRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
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

    public function testChangeQuantity(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'blog',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
        static::assertSame(1, $response['lineItems'][0]['quantity']);

        $this->browser
            ->request(
                'PATCH',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'quantity' => 2,
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(2, $response['lineItems'][0]['quantity']);
        static::assertSame(20, $response['price']['totalPrice']);
    }

    public function testChangePayload(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'blog',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
        static::assertSame(1, $response['lineItems'][0]['quantity']);

        $this->browser
            ->request(
                'PATCH',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'payload' => [
                                'test' => 'test',
                            ],
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('test', $response['lineItems'][0]['payload']);

        static::assertSame('test', $response['lineItems'][0]['payload']['test']);
        static::assertSame(10, $response['price']['totalPrice']);
    }

    public function testChangeQuantityWhenNotStackable(): void
    {
        $this->enableAdminAccess();

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'custom',
                            'label' => 'Test',
                            'referencedId' => $this->ids->get('p1'),
                            'stackable' => false,
                            'priceDefinition' => [
                                'price' => 100.0,
                                'type' => 'quantity',
                                'precision' => 1,
                                'taxRules' => [
                                    [
                                        'taxRate' => 5,
                                        'percentage' => 100,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ])
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) json_encode($response, \JSON_THROW_ON_ERROR));

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(105, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
        static::assertSame(1, $response['lineItems'][0]['quantity']);

        $this->browser
            ->request(
                'PATCH',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'quantity' => 2,
                        ],
                    ],
                ])
            );

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('CHECKOUT__CART_LINE_ITEM_NOT_STACKABLE', $response['errors'][0]['code']);
    }

    public function testUpdateByTypeCredit(): void
    {
        $this->enableAdminAccess();

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'referencedId' => $this->ids->get('p1'),
                            'label' => 'item',
                            'type' => 'credit',
                            'priceDefinition' => [
                                'price' => 100.0,
                                'type' => 'absolute',
                                'absolute' => 1.0,
                            ],
                            'stackable' => true,
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'PATCH',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'label' => 'item update',
                        ],
                    ],
                ])
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame('item update', $response['lineItems'][0]['label']);
    }

    public function testUpdateCartShouldChangePriceWithPriceDefinition(): void
    {
        $this->enableAdminAccess();

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'referencedId' => $this->ids->get('p1'),
                            'label' => 'Test',
                            'type' => 'blog',
                            'priceDefinition' => null,
                            'stackable' => true,
                            'quantity' => 1,
                            'removable' => true,
                            'channelId' => $this->ids->get('sales-channel'),
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'PATCH',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'referencedId' => $this->ids->get('p1'),
                            'label' => 'Test',
                            'type' => 'blog',
                            'priceDefinition' => [
                                'apiAlias' => 'cart_price_quantity',
                                'isCalculated' => true,
                                'listPrice' => null,
                                'precision' => 2,
                                'price' => 750,
                                'quantity' => 1,
                                'referencePriceDefinition' => null,
                                'taxRules' => [
                                    [
                                        'taxRate' => 5,
                                        'percentage' => 100,
                                    ],
                                ],
                                'type' => 'quantity',
                            ],
                            'stackable' => true,
                            'quantity' => 1,
                            'removable' => true,
                            'channelId' => $this->ids->get('sales-channel'),
                        ],
                    ],
                ])
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame(750, $response['lineItems'][0]['price']['unitPrice']);
    }

    public function testUpdateCartShouldShowAdvancedPriceWithoutPriceDefinition(): void
    {
        $this->enableAdminAccess();

        Context::createDefaultContext()->setRuleIds([
            $this->ids->create('rule-a'),
            $this->ids->create('rule-b'),
        ]);

        $data = [
            'id' => $this->ids->create('blog'),
            'blogNumber' => $this->ids->get('blog'),
            'stock' => 10,
            'name' => 'Test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
            ],
            'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
            'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 17, 'name' => 'with id'],
            'active' => true,
            'visibilities' => [
                ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
            'prices' => [
                [
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $this->ids->get('rule-a'),
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 1111, 'net' => 1111, 'linked' => false],
                    ],
                ],
                [
                    'quantityStart' => 1,
                    'ruleId' => $this->ids->get('rule-b'),
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 2222, 'net' => 2222, 'linked' => false],
                    ],
                ],
            ],
        ];

        static::getContainer()->get('rule.repository')->create([
            ['id' => $this->ids->get('rule-a'), 'name' => 'testA', 'priority' => 1, 'payload' => serialize(new AndRule([new CartAmountRule(Rule::OPERATOR_GTE, 0)]))],
            ['id' => $this->ids->get('rule-b'), 'name' => 'testB', 'priority' => 2, 'payload' => serialize(new AndRule([new CartAmountRule(Rule::OPERATOR_NEQ, 0)]))],
        ], Context::createDefaultContext());

        static::getContainer()->get('blog.repository')->create(
            [$data],
            Context::createDefaultContext()
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('blog'),
                            'referencedId' => $this->ids->get('blog'),
                            'label' => 'Test',
                            'type' => 'blog',
                            'priceDefinition' => null,
                            'stackable' => true,
                            'quantity' => 1,
                            'removable' => true,
                            'channelId' => $this->ids->get('sales-channel'),
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $this->browser
            ->request(
                'PATCH',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('blog'),
                            'referencedId' => $this->ids->get('blog'),
                            'label' => 'Test',
                            'type' => 'blog',
                            'priceDefinition' => null,
                            'stackable' => true,
                            'quantity' => 1,
                            'removable' => true,
                            'channelId' => $this->ids->get('sales-channel'),
                        ],
                    ],
                ])
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame(2222, $response['lineItems'][0]['price']['unitPrice']);
    }

    private function createTestData(): void
    {
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
                'name' => 'Test2',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
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
