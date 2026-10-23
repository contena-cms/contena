<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\CartPersister;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Content\Blog\Cart\BlogCartProcessor;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Contena\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\EventListener\Acl\CreditOrderLineItemListener;
use Contena\Core\Framework\Api\Exception\MissingPrivilegeException;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Contena\Core\Framework\Test\TestCaseHelper\TestUser;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\DeliveryTime\DeliveryTimeEntity;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Integration\Helper\MailEventListener;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\Stub\Rule\TrueRule;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;

/**
 * @internal
 */
class ChannelProxyControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use PromotionTestFixtureBehaviour;

    /**
     * @var EntityRepository<PromotionCollection>
     */
    protected EntityRepository $promotionRepository;

    private Context $context;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    private Connection $connection;

    private ChannelContextPersister $contextPersister;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->channelRepository = static::getContainer()->get('channel.repository');
        $this->promotionRepository = static::getContainer()->get('promotion.repository');
        $this->context = Context::createDefaultContext();
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $eventDispatcher = new EventDispatcher();
        $this->contextPersister = new ChannelContextPersister($this->connection, $eventDispatcher, static::getContainer()->get(CartPersister::class), new NativeClock());
        $this->ids = new IdsCollection();
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('checkoutProxyRoutesProvider')]
    public function testCheckoutProxyRoutesRequireOrderUpdatePrivilege(string $path, array $payload): void
    {
        $browser = $this->getBrowser(true, [], []);
        $browser->jsonRequest('PATCH', $path, $payload, [
            'HTTP_SW_CONTEXT_TOKEN' => Uuid::randomHex(),
        ]);

        $content = (string) $browser->getResponse()->getContent();
        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), $content);
        static::assertSame(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, $response['errors'][0]['code'] ?? null, $content);
    }

    /**
     * @return \Generator<string, array{string, array<string, mixed>}>
     */
    public static function checkoutProxyRoutesProvider(): \Generator
    {
        yield 'modify shipping costs' => [
            '/api/_proxy/modify-shipping-costs',
            [
                'channelId' => Uuid::randomHex(),
                'shippingCosts' => [
                    'unitPrice' => 1,
                    'totalPrice' => 1,
                ],
            ],
        ];

        yield 'disable automatic promotions' => [
            '/api/_proxy/disable-automatic-promotions',
            ['channelId' => Uuid::randomHex()],
        ];

        yield 'enable automatic promotions' => [
            '/api/_proxy/enable-automatic-promotions',
            ['channelId' => Uuid::randomHex()],
        ];
    }

    public function testProxyWithInvalidChannelId(): void
    {
        $this->getBrowser()->request('GET', $this->getUrl(Uuid::randomHex(), '/blog'));

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('FRAMEWORK__INVALID_CHANNEL', $response['errors'][0]['code'] ?? null);
    }

    public function testProxyCallToChannelApi(): void
    {
        $channel = $this->createChannel();

        $this->getBrowser()->request('GET', $this->getUrl($channel['id'], '/blog'));

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('errors', $response);
    }

    public function testOnlyNonContextHeadersAreCopied(): void
    {
        $channel = $this->createChannel();
        $uuid = Uuid::randomHex();

        $this->getBrowser()->request(
            'GET',
            $this->getUrl($channel['id'], '/blog'),
            [],
            [],
            [
                'HTTP_SW_CONTEXT_TOKEN' => $uuid,
                'HTTP_SW_LANGUAGE_ID' => $uuid,
                'HTTP_SW_VERSION_ID' => $uuid,
            ]
        );

        static::assertSame($uuid, $this->getBrowser()->getRequest()->headers->get('sw-context-token'));
        static::assertSame($uuid, $this->getBrowser()->getRequest()->headers->get('sw-language-id'));
        static::assertSame($uuid, $this->getBrowser()->getRequest()->headers->get('sw-version-id'));
        self::assertImplicitContextTokenHeader($this->getBrowser()->getResponse(), $uuid);
        static::assertSame($uuid, $this->getBrowser()->getResponse()->headers->get('sw-language-id'));
        static::assertSame($uuid, $this->getBrowser()->getResponse()->headers->get('sw-version-id'));
    }

    public function testOnlyDefinedHeadersAreCopied(): void
    {
        $channel = $this->createChannel();

        $this->getBrowser()->request(
            'GET',
            $this->getUrl($channel['id'], '/blog'),
            [],
            [],
            [
                'HTTP_SW_CUSTOM_HEADER' => 'foo',
            ]
        );

        static::assertSame('foo', $this->getBrowser()->getRequest()->headers->get('sw-custom-header'));
        static::assertArrayNotHasKey('sw-custom-header', $this->getBrowser()->getResponse()->headers->all());
    }

    public function testDifferentLanguage(): void
    {
        $langId = Uuid::randomHex();
        $channel = $this->createChannel();
        $this->createLanguage($langId, $channel['id']);

        $this->assertTranslation(
            ['name' => 'not translated', 'translated' => ['name' => 'not translated']],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            $channel['id'],
            Defaults::LANGUAGE_SYSTEM
        );

        $this->assertTranslation(
            ['name' => 'translated', 'translated' => ['name' => 'translated']],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            $channel['id'],
            $langId
        );

        $this->assertTranslation(
            ['name' => 'translated', 'translated' => ['name' => 'translated']],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            $channel['id'],
            $langId
        );
    }

    public function testUpdatingPromotionAfterUpdateBlogLineItem(): void
    {
        $channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $blogId = Uuid::randomHex();
        $promotionCode = 'BF99';

        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $channelContext);

        $browser = $this->createCart(TestDefaults::CHANNEL);

        $this->addBlog($browser, TestDefaults::CHANNEL, $blogId);

        // Save promotion to database
        $this->createTestFixturePercentagePromotion(Uuid::randomHex(), $promotionCode, 100, null, static::getContainer());

        // Add promotion code to our cart (not existing in DB)
        $this->addPromotionCodeByAPI($browser, TestDefaults::CHANNEL, $promotionCode);

        $cart = $this->getCart($browser, TestDefaults::CHANNEL);

        $this->updateLineItemQuantity($browser, TestDefaults::CHANNEL, $cart['lineItems'][0]['id'], 3);

        $cart = $this->getCart($browser, TestDefaults::CHANNEL);
        static::assertCount(2, $cart['lineItems']);
    }

    public function testSwitchUserWithoutChannelId(): void
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $channelContext = $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);
        $userId = $this->createUser($channelContext, 'info@example.com');

        $this->getBrowser()->request('PATCH', $this->getRootProxyUrl('/switch-user'), [
            'userId' => $userId,
        ]);

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertSame('FRAMEWORK__API_CHANNEL_ID_PARAMETER_IS_MISSING', $response['errors'][0]['code'] ?? null);
    }

    public function testSwitchUserWithInvalidChannelId(): void
    {
        $channelId = Uuid::randomHex();
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $channelContext = $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);
        $userId = $this->createUser($channelContext, 'info@example.com');
        $this->getBrowser()->request('PATCH', $this->getRootProxyUrl('/switch-user'), [
            'channelId' => $channelId,
            'userId' => $userId,
        ]);

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertSame('FRAMEWORK__INVALID_CHANNEL', $response['errors'][0]['code'] ?? null);
    }

    public function testSwitchUserWithoutUserId(): void
    {
        $channel = $this->createChannel();

        $this->getBrowser()->request('PATCH', $this->getRootProxyUrl('/switch-user'), [
            'channelId' => $channel['id'],
        ]);

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('FRAMEWORK__API_CHANNEL_ID_PARAMETER_IS_MISSING', $response['errors'][0]['code'] ?? null);
    }

    public function testSwitchUserWithInvalidUserId(): void
    {
        $channel = $this->createChannel();

        $this->getBrowser()->request('PATCH', $this->getRootProxyUrl('/switch-user'), [
            'channelId' => $channel['id'],
            'userId' => Uuid::randomHex(),
        ]);

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertSame('/userId', $response['errors'][0]['source']['pointer']);
    }

    public function testSwitchUser(): void
    {
        $channel = $this->createChannel();

        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $channelContext = $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);
        $userId = $this->createUser($channelContext, 'info@example.com');

        $browser = $this->createCart($channel['id']);

        $browser->request('PATCH', $this->getRootProxyUrl('/switch-user'), [
            'channelId' => $channel['id'],
            'userId' => $userId,
        ]);

        $response = $this->getBrowser()->getResponse();

        $contextTokenHeaderName = $this->getContextTokenHeaderName();
        $contextToken = $browser->getServerParameter($contextTokenHeaderName);
        static::assertIsString($contextToken);
        self::assertImplicitContextTokenHeader($response, $contextToken);

        static::assertIsString($channel['id']);
        // assert user is updated in database
        $payload = $this->contextPersister->load($contextToken, $channel['id']);
        static::assertArrayHasKey('userId', $payload);
        static::assertSame($userId, $payload['userId']);
        static::assertArrayHasKey('permissions', $payload);
        static::assertArrayHasKey('allowBlogPriceOverwrites', $payload['permissions']);
        static::assertTrue($payload['permissions']['allowBlogPriceOverwrites']);
    }

    public function testSwitchUserWithPermissions(): void
    {
        $channel = $this->createChannel();
        static::assertIsString($channel['id']);

        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $channelContext = $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);
        $userId = $this->createUser($channelContext, 'info@example.com');

        $permissions = [
            'allowBlogPriceOverwrites',
            'allowBlogLabelOverwrites',
            'skipBlogRecalculation',
            'skipDeliveryPriceRecalculation',
            'skipDeliveryTaxRecalculation',
            'skipPromotion',
            'skipAutomaticPromotions',
            'skipBlogStockValidation',
            'keepInactiveBlog',
        ];

        $browser = $this->createCart($channel['id']);

        $browser->request('PATCH', $this->getRootProxyUrl('/switch-user'), [
            'channelId' => $channel['id'],
            'userId' => $userId,
            'permissions' => $permissions,
        ]);

        // assert permissions exist in payload
        $contextToken = $browser->getServerParameter($this->getContextTokenHeaderName());
        static::assertIsString($contextToken);
        $payload = $this->contextPersister->load($contextToken, $channel['id']);
        static::assertArrayHasKey('permissions', $payload);
        static::assertEquals(\array_fill_keys($permissions, true), $payload['permissions']);
    }

    public function testModifyShippingCostsWithoutChannelId(): void
    {
        $this->getBrowser()->request('PATCH', $this->getRootProxyUrl('/modify-shipping-costs'), [
            'shippingCosts' => [
                'unitPrice' => 20,
                'totalPrice' => 20,
            ],
        ]);

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('FRAMEWORK__API_CHANNEL_ID_PARAMETER_IS_MISSING', $response['errors'][0]['code'] ?? null);
    }

    public function testModifyShippingCostsWithoutShippingCosts(): void
    {
        $channel = $this->createChannel();
        $browser = $this->getBrowser();
        $browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', Uuid::randomHex());
        $browser->request('PATCH', $this->getRootProxyUrl('/modify-shipping-costs'), [
            'shippingCosts' => [],
            'channelId' => $channel['id'],
        ]);

        $response = $browser->getResponse()->getContent();
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(2, $response['errors']);
        static::assertSame('/unitPrice', $response['errors'][0]['source']['pointer']);
        static::assertSame('/totalPrice', $response['errors'][1]['source']['pointer']);
    }

    public function testModifyShippingCostsWithInvalidShippingCosts(): void
    {
        $channel = $this->createChannel();
        $browser = $this->getBrowser();
        $browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', Uuid::randomHex());
        $browser->request('PATCH', $this->getRootProxyUrl('/modify-shipping-costs'), [
            'shippingCosts' => [
                'unitPrice' => 'not_numeric',
                'totalPrice' => -10,
            ],
            'channelId' => $channel['id'],
        ]);

        $response = $browser->getResponse()->getContent();
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(2, $response['errors']);
        static::assertSame('/unitPrice', $response['errors'][0]['source']['pointer']);
        static::assertSame('/totalPrice', $response['errors'][1]['source']['pointer']);
    }

    public function testModifyShippingCosts(): void
    {
        $channelContext = $this->createDefaultChannelContext();
        $blogId = Uuid::randomHex();
        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $channelContext);

        $browser = $this->createCart(TestDefaults::CHANNEL, $channelContext->getToken());
        $this->addBlog($browser, TestDefaults::CHANNEL, $blogId);

        $browser->jsonRequest(
            'PATCH',
            $this->getRootProxyUrl('/modify-shipping-costs'),
            [
                'shippingCosts' => [
                    'unitPrice' => 20,
                    'totalPrice' => 20,
                ],
                'channelId' => TestDefaults::CHANNEL,
            ],
        );

        $response = $this->getBrowser()->getResponse();
        $contextToken = $browser->getServerParameter($this->getContextTokenHeaderName());
        static::assertIsString($contextToken);

        // assert response format
        self::assertImplicitContextTokenHeader($response, $contextToken);

        $cart = $this->getCart($browser, TestDefaults::CHANNEL);

        // assert shipping costs in cart
        static::assertArrayHasKey('unitPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertSame(20, $cart['deliveries'][0]['shippingCosts']['unitPrice']);

        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertSame(20, $cart['deliveries'][0]['shippingCosts']['totalPrice']);

        // create a new shipping method and request to change
        $shippingMethodId = $this->createShippingMethod();

        $browser->jsonRequest(
            'PATCH',
            $this->getUrl(TestDefaults::CHANNEL, '/context'),
            [
                'shippingMethodId' => $shippingMethodId,
            ]
        );

        // assert response format
        $response = $this->getBrowser()->getResponse();
        $contextToken = $browser->getServerParameter($this->getContextTokenHeaderName());
        static::assertIsString($contextToken);
        self::assertImplicitContextTokenHeader($response, $contextToken);

        $cart = $this->getCart($browser, TestDefaults::CHANNEL);

        // assert shipping method in cart is changed but shipping costs in cart is not changed
        static::assertArrayHasKey('name', $cart['deliveries'][0]['shippingMethod']);
        static::assertSame('Test shipping method', $cart['deliveries'][0]['shippingMethod']['name']);

        static::assertArrayHasKey('unitPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertSame(20, $cart['deliveries'][0]['shippingCosts']['unitPrice']);

        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertSame(20, $cart['deliveries'][0]['shippingCosts']['totalPrice']);
    }

    public function testModifyShippingWith0Costs(): void
    {
        $shippingMethodId = Uuid::randomHex();

        static::getContainer()->get('shipping_method.repository')->create([
            [
                'id' => $shippingMethodId,
                'name' => 'Example shipping',
                'technicalName' => 'shipping_test',
                'availabilityRule' => ['name' => 'test', 'priority' => 1],
                'deliveryTime' => ['name' => 'test', 'min' => 1, 'max' => 1, 'unit' => 'day'],
                'taxType' => ShippingMethodEntity::TAX_TYPE_AUTO,
                'prices' => [
                    [
                        'currencyPrice' => [
                            [
                                'currencyId' => Defaults::CURRENCY,
                                'gross' => 5,
                                'linked' => false,
                                'net' => 5,
                            ],
                        ],
                        'quantityStart' => 1,
                        'shippingMethodId' => $shippingMethodId,
                    ],
                ],
                'active' => true,
            ],
        ], $this->context);

        $this->channelRepository->update([
            [
                'id' => TestDefaults::CHANNEL,
                'shippingMethodId' => $shippingMethodId,
            ],
        ], $this->context);

        $channelContext = $this->createDefaultChannelContext();

        $blogId = Uuid::randomHex();
        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $channelContext);

        $browser = $this->createCart(TestDefaults::CHANNEL, $channelContext->getToken());
        $this->addBlog($browser, TestDefaults::CHANNEL, $blogId);

        $cart = $this->getCart($browser, TestDefaults::CHANNEL);

        // assert shipping method in cart is changed but shipping costs in cart is not changed
        static::assertArrayHasKey('name', $cart['deliveries'][0]['shippingMethod']);
        static::assertSame('Example shipping', $cart['deliveries'][0]['shippingMethod']['name']);

        static::assertArrayHasKey('unitPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertSame(5, $cart['deliveries'][0]['shippingCosts']['unitPrice']);

        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertSame(5, $cart['deliveries'][0]['shippingCosts']['totalPrice']);

        $browser->jsonRequest(
            'PATCH',
            $this->getRootProxyUrl('/modify-shipping-costs'),
            [
                'shippingCosts' => [
                    'unitPrice' => 0,
                    'totalPrice' => 0,
                ],
                'channelId' => TestDefaults::CHANNEL,
            ]
        );

        $response = $this->getBrowser()->getResponse();
        $contextToken = $browser->getServerParameter($this->getContextTokenHeaderName());
        static::assertIsString($contextToken);

        // assert response format
        self::assertImplicitContextTokenHeader($response, $contextToken);

        $cart = $this->getCart($browser, TestDefaults::CHANNEL);

        // assert shipping costs in cart
        static::assertArrayHasKey('unitPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertSame(0, $cart['deliveries'][0]['shippingCosts']['unitPrice']);

        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertSame(0, $cart['deliveries'][0]['shippingCosts']['totalPrice']);
    }

    public function testModifyShippingCostsManuallyInCaseCartIsEmpty(): void
    {
        $channelContext = $this->createDefaultChannelContext();

        $payload = $this->contextPersister->load($channelContext->getToken(), $channelContext->getChannelId());
        $payload[ChannelContextService::PERMISSIONS][BlogCartProcessor::ALLOW_BLOG_PRICE_OVERWRITES] = true;
        $this->contextPersister->save($channelContext->getToken(), $payload, $channelContext->getChannelId());

        $browser = $this->createCart(TestDefaults::CHANNEL);

        $firstBlogId = $this->ids->get('p1');
        $secondBlogId = $this->ids->get('p2');
        $this->createTestFixtureBlog($firstBlogId, 119, 19, static::getContainer(), $channelContext);
        $this->createTestFixtureBlog($secondBlogId, 200, 10, static::getContainer(), $channelContext);

        $this->addSingleLineItem($browser, TestDefaults::CHANNEL, [
            'id' => $firstBlogId,
            'label' => $firstBlogId,
            'referencedId' => $firstBlogId,
            'quantity' => 1,
            'type' => LineItem::BLOG_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => 100,
                'taxRules' => [[
                    'taxRate' => 19,
                    'percentage' => 100,
                ]],
                'type' => 'quantity',
            ],
        ], $channelContext->getToken());

        $this->addSingleLineItem($browser, TestDefaults::CHANNEL, [
            'id' => $secondBlogId,
            'label' => $secondBlogId,
            'referencedId' => $firstBlogId,
            'quantity' => 1,
            'type' => LineItem::BLOG_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => 100,
                'taxRules' => [[
                    'taxRate' => 10,
                    'percentage' => 100,
                ]],
                'type' => 'quantity',
            ],
        ], $channelContext->getToken());

        $this->modifyShippingCostsManually($browser, 20, $channelContext->getToken());

        $cart = $this->getStoreApiCart($browser, TestDefaults::CHANNEL, $channelContext->getToken());

        $shippingCosts = $cart['deliveries'][0]['shippingCosts'];

        // shipping costs are now based on manual value, tax rate will be mixed
        static::assertArrayHasKey('unitPrice', $shippingCosts);
        static::assertSame(20, $shippingCosts['unitPrice']);

        static::assertArrayHasKey('totalPrice', $shippingCosts);
        static::assertSame(20, $shippingCosts['totalPrice']);

        static::assertCount(2, $shippingCosts['calculatedTaxes']);
        static::assertSame(19, $shippingCosts['calculatedTaxes'][0]['taxRate']);
        static::assertSame(10, $shippingCosts['calculatedTaxes'][1]['taxRate']);

        // using store-api through proxy to remove all items in cart
        $this->storeAPIRemoveLineItems($browser, [$firstBlogId, $secondBlogId], $channelContext->getToken());

        $cart = $this->getStoreApiCart($browser, TestDefaults::CHANNEL, $channelContext->getToken());
        static::assertSame([], $cart['deliveries']);

        // adding a new blog item to cart.
        $this->addSingleLineItem($browser, TestDefaults::CHANNEL, [
            'id' => $firstBlogId,
            'label' => $firstBlogId,
            'referencedId' => $firstBlogId,
            'quantity' => 1,
            'type' => LineItem::BLOG_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => 100,
                'taxRules' => [[
                    'taxRate' => 19,
                    'percentage' => 100,
                ]],
                'type' => 'quantity',
            ],
        ], $channelContext->getToken());

        $cart = $this->getStoreApiCart($browser, TestDefaults::CHANNEL, $channelContext->getToken());

        // after re-adding a line item, there is one tax rate and the manual shipping costs are restored.
        $shippingCosts = $cart['deliveries'][0]['shippingCosts'];

        static::assertArrayHasKey('unitPrice', $shippingCosts);
        static::assertSame(20, $shippingCosts['unitPrice']);

        static::assertArrayHasKey('totalPrice', $shippingCosts);
        static::assertSame(20, $shippingCosts['totalPrice']);

        static::assertCount(1, $shippingCosts['calculatedTaxes']);
        static::assertSame(19, $shippingCosts['calculatedTaxes'][0]['taxRate']);
    }

    public function testModifyShippingCostsManuallyInCaseCartIsNotEmpty(): void
    {
        $channelContext = $this->createDefaultChannelContext();

        $payload = $this->contextPersister->load($channelContext->getToken(), $channelContext->getChannelId());
        $payload[ChannelContextService::PERMISSIONS][BlogCartProcessor::ALLOW_BLOG_PRICE_OVERWRITES] = true;
        $this->contextPersister->save($channelContext->getToken(), $payload, $channelContext->getChannelId());

        $browser = $this->createCart(TestDefaults::CHANNEL);

        $firstBlogId = $this->ids->get('p1');
        $secondBlogId = $this->ids->get('p2');
        $this->createTestFixtureBlog($firstBlogId, 119, 19, static::getContainer(), $channelContext);
        $this->createTestFixtureBlog($secondBlogId, 200, 10, static::getContainer(), $channelContext);

        $this->addSingleLineItem($browser, TestDefaults::CHANNEL, [
            'id' => $firstBlogId,
            'label' => $firstBlogId,
            'referencedId' => $firstBlogId,
            'quantity' => 1,
            'type' => LineItem::BLOG_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => 100,
                'taxRules' => [[
                    'taxRate' => 19,
                    'percentage' => 100,
                ]],
                'type' => 'quantity',
            ],
        ], $channelContext->getToken());

        $this->addSingleLineItem($browser, TestDefaults::CHANNEL, [
            'id' => $secondBlogId,
            'label' => $secondBlogId,
            'referencedId' => $firstBlogId,
            'quantity' => 1,
            'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => -100,
                'type' => 'absolute',
            ],
        ], $channelContext->getToken());

        $this->modifyShippingCostsManually($browser, 20, $channelContext->getToken());

        $cart = $this->getStoreApiCart($browser, TestDefaults::CHANNEL, $channelContext->getToken());

        $shippingCosts = $cart['deliveries'][0]['shippingCosts'];

        // shipping costs are now based on manual value, there is one tax rate
        static::assertArrayHasKey('unitPrice', $shippingCosts);
        static::assertSame(20, $shippingCosts['unitPrice']);

        static::assertArrayHasKey('totalPrice', $shippingCosts);
        static::assertSame(20, $shippingCosts['totalPrice']);

        static::assertCount(1, $shippingCosts['calculatedTaxes']);
        static::assertSame(19, $shippingCosts['calculatedTaxes'][0]['taxRate']);

        // using store-api through proxy to remove Blog item in cart, keep Credit item.
        $this->storeAPIRemoveLineItems($browser, [$firstBlogId], $channelContext->getToken());

        // adding a new blog item to cart.
        $this->addSingleLineItem($browser, TestDefaults::CHANNEL, [
            'id' => $firstBlogId,
            'label' => $firstBlogId,
            'referencedId' => $firstBlogId,
            'quantity' => 1,
            'type' => LineItem::BLOG_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => 100,
                'taxRules' => [[
                    'taxRate' => 19,
                    'percentage' => 100,
                ]],
                'type' => 'quantity',
            ],
        ], $channelContext->getToken());

        $cart = $this->getStoreApiCart($browser, TestDefaults::CHANNEL, $channelContext->getToken());

        // shipping costs is still based on manual value
        $shippingCosts = $cart['deliveries'][0]['shippingCosts'];
        static::assertArrayHasKey('unitPrice', $shippingCosts);
        static::assertSame(20, $shippingCosts['unitPrice']);

        static::assertArrayHasKey('totalPrice', $shippingCosts);
        static::assertSame(20, $shippingCosts['totalPrice']);

        static::assertCount(1, $shippingCosts['calculatedTaxes']);
        static::assertSame(19, $shippingCosts['calculatedTaxes'][0]['taxRate']);
    }

    public function testSwitchDeliveryMethodAndPriceWillBeCalculated(): void
    {
        $channelContext = $this->createDefaultChannelContext();
        $blogId = Uuid::randomHex();
        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $channelContext);

        $browser = $this->createCart(TestDefaults::CHANNEL, $channelContext->getToken());
        $this->addBlog($browser, TestDefaults::CHANNEL, $blogId);
        $cart = $this->getCart($browser, TestDefaults::CHANNEL);

        // assert shipping cost in cart is default from sales channel
        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertSame(0, $cart['deliveries'][0]['shippingCosts']['totalPrice']);

        // create a new shipping method and request to change
        $shippingMethodId = $this->createShippingMethod();
        $browser->request('PATCH', $this->getUrl(TestDefaults::CHANNEL, '/context'), [
            'shippingMethodId' => $shippingMethodId,
        ]);

        // assert response format
        $response = $this->getBrowser()->getResponse();
        self::assertImplicitContextTokenHeader($response, $channelContext->getToken());

        $cart = $this->getCart($browser, TestDefaults::CHANNEL);

        // assert shipping method and cost are changed
        static::assertArrayHasKey('name', $cart['deliveries'][0]['shippingMethod']);
        static::assertSame('Test shipping method', $cart['deliveries'][0]['shippingMethod']['name']);

        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertSame(30, $cart['deliveries'][0]['shippingCosts']['totalPrice']);
    }

    public function testCreditItemProcessorTakeCustomItemIntoAccount(): void
    {
        $channelContext = $this->createDefaultChannelContext();
        $blogId = $this->ids->get('p1');
        $payload = $this->contextPersister->load($channelContext->getToken(), $channelContext->getChannelId());
        $payload[ChannelContextService::PERMISSIONS][BlogCartProcessor::ALLOW_BLOG_PRICE_OVERWRITES] = true;
        $this->contextPersister->save($channelContext->getToken(), $payload, $channelContext->getChannelId());

        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $channelContext);

        $browser = $this->createCart(TestDefaults::CHANNEL);

        $taxForCustomItem = 20;
        $taxForBlogItem = 10;

        $this->addSingleLineItem($browser, TestDefaults::CHANNEL, [
            'id' => $blogId,
            'label' => $blogId,
            'referencedId' => $blogId,
            'quantity' => 1,
            'type' => LineItem::BLOG_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => 100,
                'taxRules' => [[
                    'taxRate' => $taxForBlogItem,
                    'percentage' => 100,
                ]],
                'type' => 'quantity',
            ],
        ], $channelContext->getToken());

        $this->addSingleLineItem($browser, TestDefaults::CHANNEL, [
            'id' => $this->ids->get('p2'),
            'label' => $this->ids->get('p2'),
            'referencedId' => $this->ids->get('p2'),
            'quantity' => 1,
            'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => 100,
                'taxRules' => [[
                    'taxRate' => $taxForCustomItem,
                    'percentage' => 100,
                ]],
                'type' => 'quantity',
            ],
        ], $channelContext->getToken());

        $this->addSingleLineItem($browser, TestDefaults::CHANNEL, [
            'id' => $this->ids->get('p3'),
            'label' => $this->ids->get('p3'),
            'referencedId' => $this->ids->get('p3'),
            'quantity' => 1,
            'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => -100,
                'type' => 'absolute',
            ],
        ], $channelContext->getToken());

        $cart = $this->getStoreApiCart($browser, TestDefaults::CHANNEL, $channelContext->getToken());

        // assert there are 3 items in cart
        static::assertArrayHasKey('lineItems', $cart);
        static::assertCount(3, $cart['lineItems']);

        $creditLineItems = array_filter($cart['lineItems'], static fn ($lineItem) => $lineItem['type'] === LineItem::CREDIT_LINE_ITEM_TYPE);

        // assert there is credit item in cart
        static::assertNotCount(0, $creditLineItems);
        $creditLineItem = array_values($creditLineItems)[0];

        // assert there is calculated taxes for blog and custom items in cart
        static::assertCount(2, $calculatedTaxes = $creditLineItem['price']['calculatedTaxes']);
        $calculatedTaxForCustomItem = array_filter($calculatedTaxes, static fn ($tax) => $tax['taxRate'] === $taxForCustomItem);

        static::assertNotCount(0, $calculatedTaxForCustomItem);
        static::assertCount(1, $calculatedTaxForCustomItem);

        $calculatedTaxForBlogItem = array_filter($calculatedTaxes, static fn ($tax) => $tax['taxRate'] === $taxForBlogItem);

        static::assertNotCount(0, $calculatedTaxForBlogItem);
        static::assertCount(1, $calculatedTaxForBlogItem);
    }

    public function testDisableAutomaticPromotions(): void
    {
        $channelContext = $this->createDefaultChannelContext();
        $this->createTestFixtureFixedDiscountPromotion(Uuid::randomHex(), 40, PromotionDiscountEntity::SCOPE_CART, null, static::getContainer(), $channelContext);

        $browser = $this->createCart(TestDefaults::CHANNEL, $channelContext->getToken());

        $blogId = Uuid::randomHex();
        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $channelContext);
        $this->addBlog($browser, TestDefaults::CHANNEL, $blogId);

        // There are 2 line items in cart including 1 blog and 1 automatic promotion
        $cart = $this->getCart($browser, TestDefaults::CHANNEL);
        static::assertCount(2, $cart['lineItems']);
        static::assertSame('blog', $cart['lineItems'][0]['type']);
        static::assertSame('promotion', $cart['lineItems'][1]['type']);

        // Call to disable automatic promotions
        $browser->request(
            'PATCH',
            $this->getRootProxyUrl('/disable-automatic-promotions'),
            ['channelId' => $channelContext->getChannelId()]
        );
        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        // There is 1 line item in cart. It is blog
        $cart = $this->getCart($browser, TestDefaults::CHANNEL);
        static::assertCount(1, $cart['lineItems']);
        static::assertNotSame('promotion', $cart['lineItems'][0]['type']);
    }

    public function testDisableAutomaticPromotionDoesNotAffectPromotionCodes(): void
    {
        $channelContext = $this->createDefaultChannelContext();
        $this->createTestFixtureFixedDiscountPromotion(Uuid::randomHex(), 40, PromotionDiscountEntity::SCOPE_CART, null, static::getContainer(), $channelContext);

        $browser = $this->createCart(TestDefaults::CHANNEL, $channelContext->getToken());

        $blogId = Uuid::randomHex();
        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $channelContext);
        $this->addBlog($browser, TestDefaults::CHANNEL, $blogId);

        // Add promotion code into cart
        $promotionCode = Random::getAlphanumericString(5);
        $this->createTestFixtureAbsolutePromotion(Uuid::randomHex(), $promotionCode, 100, static::getContainer());
        $this->addPromotionCodeByAPI($browser, TestDefaults::CHANNEL, $promotionCode);

        // Check there are automatic promotion and promotion code in cart
        $cart = $this->getCart($browser, TestDefaults::CHANNEL);
        static::assertCount(3, $cart['lineItems']);
        static::assertSame('blog', $cart['lineItems'][0]['type']);
        static::assertSame('promotion', $cart['lineItems'][1]['type']);
        static::assertSame('promotion', $cart['lineItems'][2]['type']);
        static::assertSame($promotionCode, $cart['lineItems'][2]['referencedId']);

        // Call to disable automatic promotion
        $browser->request(
            'PATCH',
            $this->getRootProxyUrl('/disable-automatic-promotions'),
            ['channelId' => $channelContext->getChannelId()]
        );

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        // Check automatic promotion code is disabled and exist the promotion code in cart
        $cart = $this->getCart($browser, TestDefaults::CHANNEL);
        static::assertCount(2, $cart['lineItems']);
        static::assertSame($promotionCode, $cart['lineItems'][1]['referencedId']);
    }

    public function testEnableAutomaticPromotions(): void
    {
        $channelContext = $this->createDefaultChannelContext();
        $this->createTestFixtureFixedDiscountPromotion(Uuid::randomHex(), 40, PromotionDiscountEntity::SCOPE_CART, null, static::getContainer(), $channelContext);

        $browser = $this->createCart(TestDefaults::CHANNEL, $channelContext->getToken());

        $blogId = Uuid::randomHex();
        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $channelContext);
        $this->addBlog($browser, TestDefaults::CHANNEL, $blogId);

        $cart = $this->getCart($browser, TestDefaults::CHANNEL);

        static::assertCount(2, $cart['lineItems']);
        static::assertSame('blog', $cart['lineItems'][0]['type']);
        static::assertSame('promotion', $cart['lineItems'][1]['type']);

        // Call to disable automatic promotion
        $browser->request(
            'PATCH',
            $this->getRootProxyUrl('/disable-automatic-promotions'),
            ['channelId' => $channelContext->getChannelId()]
        );

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        // Check automatic promotion is disabled
        $cart = $this->getCart($browser, TestDefaults::CHANNEL);
        static::assertCount(1, $cart['lineItems']);
        static::assertNotSame('promotion', $cart['lineItems'][0]['type']);

        // Call to enable automatic promotion
        $browser->request(
            'PATCH',
            $this->getRootProxyUrl('/enable-automatic-promotions'),
            ['channelId' => $channelContext->getChannelId()]
        );

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        // Check automatic promotion is enabled
        $cart = $this->getCart($browser, TestDefaults::CHANNEL);
        static::assertCount(2, $cart['lineItems']);
        static::assertSame('blog', $cart['lineItems'][0]['type']);
        static::assertSame('promotion', $cart['lineItems'][1]['type']);
    }

    public function testProxyCreateOrderWithInvalidChannelId(): void
    {
        $this->getBrowser()->request('POST', $this->getCreateOrderApiUrl(Uuid::randomHex()));

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('FRAMEWORK__INVALID_CHANNEL', $response['errors'][0]['code'] ?? null);
    }

    #[DataProvider('sendOrderConfirmationMailFlagProvider')]
    public function testProxyCreateOrderHonorsSendOrderConfirmationMailFlag(bool $sendOrderConfirmationMail, bool $mailExpected): void
    {
        $channelContext = $this->createDefaultChannelContext();
        $userId = $this->createUser($channelContext, Uuid::randomHex() . '@example.com');
        $payload = $this->contextPersister->load($channelContext->getToken(), $channelContext->getChannelId());
        $payload = array_merge($payload, [
            'userId' => $userId,
            'paymentMethodId' => $this->getAvailablePaymentMethod()->getId(),
        ]);
        $this->contextPersister->save($channelContext->getToken(), $payload, $channelContext->getChannelId());

        $blogId = Uuid::randomHex();
        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $channelContext);

        $browser = $this->createCart(TestDefaults::CHANNEL, $channelContext->getToken());
        $this->addBlog($browser, TestDefaults::CHANNEL, $blogId);

        $this->mailListener(function (MailEventListener $listener) use ($browser, $channelContext, $sendOrderConfirmationMail, $mailExpected): void {
            $browser->jsonRequest('POST', $this->getCreateOrderApiUrl($channelContext->getChannelId()), [
                'sendOrderConfirmationMail' => $sendOrderConfirmationMail,
            ]);

            static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());

            static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

            static::assertSame($mailExpected, $listener->sent('order_confirmation_mail'));
        });
    }

    public function testProxyCreateOrderPrivileges(): void
    {
        try {
            $channelContext = $this->createDefaultChannelContext();
            $userId = $this->createUser($channelContext, 'info@example.com');
            $blogId = $this->ids->get('p1');
            $payload = $this->contextPersister->load($channelContext->getToken(), $channelContext->getChannelId());
            $payload[ChannelContextService::PERMISSIONS][BlogCartProcessor::ALLOW_BLOG_PRICE_OVERWRITES] = true;
            $payload = array_merge($payload, [
                'userId' => $userId,
                'paymentMethodId' => $this->getAvailablePaymentMethod()->getId(),
            ]);
            $this->contextPersister->save($channelContext->getToken(), $payload, $channelContext->getChannelId());

            $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $channelContext);

            $browser = $this->createCart(TestDefaults::CHANNEL, $channelContext->getToken());

            $this->addSingleLineItem($browser, TestDefaults::CHANNEL, [
                'id' => $blogId,
                'label' => $blogId,
                'referencedId' => $blogId,
                'quantity' => 1,
                'type' => LineItem::BLOG_LINE_ITEM_TYPE,
                'priceDefinition' => [
                    'price' => 100,
                    'taxRules' => [[
                        'taxRate' => 10,
                        'percentage' => 100,
                    ]],
                    'type' => 'quantity',
                ],
            ], $channelContext->getToken());

            $this->addSingleLineItem($browser, TestDefaults::CHANNEL, [
                'id' => $this->ids->get('p2'),
                'label' => $this->ids->get('p2'),
                'referencedId' => $this->ids->get('p2'),
                'quantity' => 1,
                'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
                'priceDefinition' => [
                    'price' => -100,
                    'type' => 'absolute',
                ],
            ], $channelContext->getToken());

            $cart = $this->getCart($browser, TestDefaults::CHANNEL);

            static::assertCount(2, $cart['lineItems']);
            static::assertSame('blog', $cart['lineItems'][0]['type']);
            static::assertSame('credit', $cart['lineItems'][1]['type']);

            $orderPrivileges = [
                'api_proxy_switch-user',
                'order:create',
                'order_customer:create',
                'order_address:create',
                'order_delivery:create',
                'order_line_item:create',
                'order_transaction:create',
                'order_delivery_position:create',
                'mail_template_type:update',
                'user:update',
            ];
            foreach ([true, false] as $testOrderOnly) {
                TestUser::createNewTestUser(
                    $browser->getContainer()->get(Connection::class),
                    $testOrderOnly ? $orderPrivileges : ['api_proxy_switch-user', CreditOrderLineItemListener::ACL_ORDER_CREATE_DISCOUNT_PRIVILEGE],
                )->authorizeBrowser($browser);
                $browser->request('POST', $this->getCreateOrderApiUrl($channelContext->getChannelId()));

                $response = $browser->getResponse()->getContent();
                $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

                static::assertArrayHasKey('errors', $response, print_r($response, true));
                static::assertSame('FRAMEWORK__MISSING_PRIVILEGE_ERROR', $response['errors'][0]['code'] ?? null);
                static::assertStringContainsString(
                    $testOrderOnly ? CreditOrderLineItemListener::ACL_ORDER_CREATE_DISCOUNT_PRIVILEGE : 'order_line_item:create',
                    $response['errors'][0]['detail'] ?? ''
                );
            }

            TestUser::createNewTestUser(
                $browser->getContainer()->get(Connection::class),
                array_merge($orderPrivileges, [CreditOrderLineItemListener::ACL_ORDER_CREATE_DISCOUNT_PRIVILEGE])
            )->authorizeBrowser($browser);
            $browser->request('POST', $this->getCreateOrderApiUrl($channelContext->getChannelId()));

            $response = $browser->getResponse();

            static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        } finally {
            $this->resetBrowser();
        }
    }

    public function testProxyCreateOrderWithHeadersAreCopied(): void
    {
        $channel = $this->createChannel();
        $uuid = Uuid::randomHex();

        $this->getBrowser()->request(
            'GET',
            $this->getUrl($channel['id'], '/blog'),
            [],
            [],
            [
                'HTTP_SW_CONTEXT_TOKEN' => $uuid,
                'HTTP_SW_LANGUAGE_ID' => $uuid,
                'HTTP_SW_VERSION_ID' => $uuid,
            ]
        );

        static::assertSame($uuid, $this->getBrowser()->getRequest()->headers->get('sw-context-token'));
        static::assertSame($uuid, $this->getBrowser()->getRequest()->headers->get('sw-language-id'));
        static::assertSame($uuid, $this->getBrowser()->getRequest()->headers->get('sw-version-id'));
        self::assertImplicitContextTokenHeader($this->getBrowser()->getResponse(), $uuid);
        static::assertSame($uuid, $this->getBrowser()->getResponse()->headers->get('sw-language-id'));
        static::assertSame($uuid, $this->getBrowser()->getResponse()->headers->get('sw-version-id'));
    }

    /**
     * @return iterable<string, array{sendOrderConfirmationMail: bool, mailExpected: bool}>
     */
    public static function sendOrderConfirmationMailFlagProvider(): iterable
    {
        yield 'send order confirmation mail by default option' => [
            'sendOrderConfirmationMail' => true,
            'mailExpected' => true,
        ];

        yield 'suppress order confirmation mail when disabled' => [
            'sendOrderConfirmationMail' => false,
            'mailExpected' => false,
        ];
    }

    private function getLangHeaderName(): string
    {
        return 'HTTP_' . mb_strtoupper(str_replace('-', '_', PlatformRequest::HEADER_LANGUAGE_ID));
    }

    /**
     * @param array<string, mixed> $expectedTranslations
     * @param array<string, mixed> $data
     */
    private function assertTranslation(
        array $expectedTranslations,
        array $data,
        string $channelId,
        ?string $langOverride = null
    ): void {
        $baseResource = '/api/category';

        $categoryData = $data;
        $categoryData['active'] = true;
        if (!isset($categoryData['id'])) {
            $categoryData['id'] = Uuid::randomHex();
        }

        $this->getBrowser()->jsonRequest('POST', $baseResource, $categoryData);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(204, $response->getStatusCode());

        $this->assertEntityExists($this->getBrowser(), 'category', $categoryData['id']);

        $headers = ['HTTP_ACCEPT' => 'application/json'];
        if ($langOverride) {
            $headers[$this->getLangHeaderName()] = $langOverride;
        }

        $this->getBrowser()->request('GET', $this->getUrl($channelId, '/category/' . $categoryData['id']), [], [], $headers);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $responseData = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        foreach ($expectedTranslations as $key => $expectedTranslation) {
            if (!\is_array($expectedTranslations[$key])) {
                static::assertSame($expectedTranslations[$key], $responseData[$key]);
            } else {
                foreach ($expectedTranslations[$key] as $key2 => $expectedTranslation2) {
                    static::assertSame($expectedTranslation[$key2], $responseData[$key][$key2]);
                }
            }
        }
    }

    private function createLanguage(string $langId, string $channelId, ?string $fallbackId = null): void
    {
        $baseUrl = '/api';

        if ($fallbackId) {
            $fallbackLocaleId = Uuid::randomHex();
            $parentLanguageData = [
                'id' => $fallbackId,
                'name' => 'test language ' . $fallbackId,
                'locale' => [
                    'id' => $fallbackLocaleId,
                    'code' => 'de-DE-' . $fallbackLocaleId,
                    'name' => 'Test locale ' . $fallbackLocaleId,
                    'territory' => 'Test territory ' . $fallbackLocaleId,
                ],
                'translationCodeId' => $fallbackLocaleId,
                'active' => true,
            ];
            $this->getBrowser()->jsonRequest('POST', $baseUrl . '/language', $parentLanguageData);
            static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());
        }

        $localeId = Uuid::randomHex();
        $languageData = [
            'id' => $langId,
            'name' => 'test language ' . $langId,
            'parentId' => $fallbackId,
            'locale' => [
                'id' => $localeId,
                'code' => 'de-DE-' . $localeId,
                'name' => 'Test locale ' . $localeId,
                'territory' => 'Test territory ' . $localeId,
            ],
            'translationCodeId' => $localeId,
            'active' => true,
            'channels' => [
                ['id' => $channelId],
            ],
        ];

        $this->getBrowser()->jsonRequest('POST', $baseUrl . '/language', $languageData);
        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());

        $this->getBrowser()->request('GET', $baseUrl . '/language/' . $langId);
    }

    private function getUrl(string $channelId, string $url): string
    {
        return \sprintf(
            '/api/_proxy/store-api/%s/%s',
            $channelId,
            ltrim($url, '/')
        );
    }

    private function getStoreApiUrl(string $channelId, string $url): string
    {
        return \sprintf(
            '/api/_proxy/store-api/%s/%s',
            $channelId,
            ltrim($url, '/')
        );
    }

    private function getCreateOrderApiUrl(string $channelId): string
    {
        return \sprintf(
            '/api/_proxy-order/%s',
            $channelId
        );
    }

    private function getRootProxyUrl(string $url): string
    {
        return \sprintf(
            '/api/_proxy/%s',
            ltrim($url, '/')
        );
    }

    /**
     * @param array<string, mixed> $channel
     *
     * @return array<string, mixed>
     */
    private function createChannel(array $channel = []): array
    {
        $defaults = [
            'id' => Uuid::randomHex(),
            'name' => 'unit test channel',
            'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'userGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ];

        $channel = array_merge_recursive($defaults, $channel);

        $this->channelRepository->create([$channel], Context::createDefaultContext());

        return $channel;
    }

    private function createCart(string $saleChannelId, ?string $contextToken = null): KernelBrowser
    {
        $contextToken ??= Uuid::randomHex();
        $this->getBrowser()->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->getBrowser()->request('POST', $this->getUrl($saleChannelId, 'checkout/cart'));

        $response = $this->getBrowser()->getResponse();

        static::assertSame(200, $response->getStatusCode());

        $browser = clone $this->getBrowser();
        $browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        return $browser;
    }

    private function addBlog(KernelBrowser $browser, string $channelId, string $id, int $quantity = 1): void
    {
        $browser->jsonRequest(
            'POST',
            $this->getUrl($channelId, 'checkout/cart/line-item'),
            [
                'items' => [
                    [
                        'type' => 'blog',
                        'referencedId' => $id,
                        'quantity' => $quantity,
                    ],
                ],
            ]
        );
    }

    /**
     * @param array<mixed> $payload
     */
    private function addSingleLineItem(KernelBrowser $browser, string $channelId, array $payload = [], ?string $contextToken = null): void
    {
        $browser->jsonRequest(
            'POST',
            $this->getStoreApiUrl($channelId, 'checkout/cart/line-item'),
            ['items' => [$payload]],
            [
                'HTTP_SW_CONTEXT_TOKEN' => $contextToken,
            ]
        );
    }

    private function modifyShippingCostsManually(KernelBrowser $browser, float $price, ?string $contextToken = null): void
    {
        $browser->jsonRequest(
            'PATCH',
            $this->getRootProxyUrl('/modify-shipping-costs'),
            [
                'shippingCosts' => [
                    'unitPrice' => $price,
                    'totalPrice' => $price,
                ],
                'channelId' => TestDefaults::CHANNEL,
            ],
            [
                'HTTP_SW_CONTEXT_TOKEN' => $contextToken,
            ],
        );
    }

    /**
     * @param array<mixed> $ids
     */
    private function storeAPIRemoveLineItems(KernelBrowser $browser, array $ids, ?string $contextToken = null): void
    {
        $browser->jsonRequest(
            'DELETE',
            $this->getStoreApiUrl(TestDefaults::CHANNEL, '/checkout/cart/line-item'),
            [
                'ids' => $ids,
            ],
            [
                'HTTP_SW_CONTEXT_TOKEN' => $contextToken,
            ],
        );
    }

    private function updateLineItemQuantity(
        KernelBrowser $browser,
        string $channelId,
        string $lineItemId,
        int $quantity
    ): void {
        $browser->jsonRequest(
            'PATCH',
            $this->getUrl($channelId, 'checkout/cart/line-item'),
            [
                'items' => [
                    [
                        'id' => $lineItemId,
                        'quantity' => $quantity,
                    ],
                ],
            ]
        );
    }

    /**
     * @return array<mixed>
     */
    private function getCart(KernelBrowser $browser, string $channelId): array
    {
        $browser->request('GET', $this->getUrl($channelId, 'checkout/cart'));

        $cart = json_decode($browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        return $cart;
    }

    /**
     * @return array<mixed>
     */
    private function getStoreApiCart(KernelBrowser $browser, string $channelId, string $contextToken): array
    {
        $browser->request('GET', $this->getStoreApiUrl($channelId, 'checkout/cart'), [], [], [
            'HTTP_SW_CONTEXT_TOKEN' => $contextToken,
        ]);

        $cart = json_decode($browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        return $cart['data'] ?? $cart;
    }

    private function addPromotionCodeByAPI(KernelBrowser $browser, string $channelId, string $code): void
    {
        $browser->jsonRequest(
            'POST',
            $this->getUrl($channelId, 'checkout/cart/line-item'),
            [
                'items' => [
                    [
                        'type' => 'promotion',
                        'referencedId' => $code,
                    ],
                ],
            ]
        );
    }

    private function createUser(
        ChannelContext $channelContext,
        string $email
    ): string {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '12345',
        ];

        $this->userRepository->create([$user], $channelContext->getContext());

        return $userId;
    }

    private function getContextTokenHeaderName(): string
    {
        return 'HTTP_' . mb_strtoupper(str_replace('-', '_', PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    private static function assertImplicitContextTokenHeader(Response $response, string $contextToken): void
    {
        if (Feature::isActive('CACHE_REWORK')) {
            static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));

            return;
        }

        static::assertSame($contextToken, $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    private function createDefaultChannelContext(): ChannelContext
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);

        return $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }

    private function mailListener(\Closure $closure): mixed
    {
        $mapping = static::getContainer()->get(Connection::class)
            ->fetchAllKeyValue('SELECT LOWER(HEX(id)), technical_name FROM mail_template_type');

        $listener = new MailEventListener($mapping);
        EventHookDispatcher::fromContainer(static::getContainer())->on(FlowSendMailActionEvent::class, $listener);

        return $closure($listener);
    }

    private function createShippingMethod(): string
    {
        $shippingMethodId = Uuid::randomHex();
        $repository = static::getContainer()->get('shipping_method.repository');

        $ruleRegistry = static::getContainer()->get(RuleConditionRegistry::class);
        $prop = new \ReflectionProperty(RuleConditionRegistry::class, 'rules');
        $prop->setValue($ruleRegistry, array_merge($prop->getValue($ruleRegistry), ['true' => new TrueRule()]));

        $data = [
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'Test shipping method',
            'technicalName' => 'shipping_test',
            'bindShippingfree' => false,
            'active' => true,
            'prices' => [
                [
                    'name' => 'Std',
                    'price' => '10.00',
                    'currencyId' => Defaults::CURRENCY,
                    'calculation' => 1,
                    'quantityStart' => 1,
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 20,
                            'gross' => 30,
                            'linked' => false,
                        ],
                    ],
                ],
            ],
            'deliveryTime' => $this->createDeliveryTime(),
            'availabilityRule' => [
                'id' => Uuid::randomHex(),
                'name' => 'true',
                'priority' => 1,
                'conditions' => [
                    [
                        'type' => (new TrueRule())->getName(),
                    ],
                ],
            ],
        ];

        $repository->create([$data], $this->context);

        $saleChannelShippingMethodRepository = static::getContainer()->get('channel_shipping_method.repository');
        $saleChannelShippingMethodRepository->create([[
            'channelId' => TestDefaults::CHANNEL,
            'shippingMethodId' => $shippingMethodId,
        ]], $this->context);

        return $shippingMethodId;
    }

    /**
     * @return array<string, string|int>
     */
    private function createDeliveryTime(): array
    {
        return [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'min' => 1,
            'max' => 90,
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
        ];
    }
}
