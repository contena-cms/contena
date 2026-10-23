<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\CartLocker;
use Contena\Core\Checkout\Cart\Event\CheckoutOrderPlacedCriteriaEvent;
use Contena\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\CountryAddToChannelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Salutation\SalutationDefinition;
use Contena\Core\System\TaxProvider\TaxProviderCollection;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestConstantTaxRateProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Group('store-api')]
class CartOrderRouteTest extends TestCase
{
    use CountryAddToChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    /**
     * @var EntityRepository<TaxProviderCollection>
     */
    private EntityRepository $taxProviderRepository;

    private string $validSalutationId;

    private string $validCountryId;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->taxProviderRepository = static::getContainer()->get('tax_provider.repository');
        $this->validSalutationId = $this->getValidSalutationId();
        $this->validCountryId = $this->getValidCountryId($this->ids->get('sales-channel'));

        $this->createTestData();
    }

    public function testOrderNotLoggedIn(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order'
            );

        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame(RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE, $response['errors'][0]['code']);
    }

    public function testOrderEmptyCart(): void
    {
        $this->createUserAndLogin();

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order'
            );

        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CART_EMPTY', $response['errors'][0]['code']);
    }

    public function testOrderOneBlog(): void
    {
        $this->createUserAndLogin();
        $this->addBlogToCart();

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order'
            );

        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('order', $response['apiAlias']);
        static::assertSame(10, $response['transactions'][0]['amount']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
    }

    public function testOrderRouteDoesNotExposePurchasePricesInLineItemPayload(): void
    {
        $this->blogRepository->update([
            [
                'id' => $this->ids->get('p1'),
                'purchasePrices' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 7.5, 'net' => 5, 'linked' => false],
                ],
            ],
        ], Context::createDefaultContext());

        $this->createUserAndLogin();
        $this->addBlogToCart();

        $this->browser->request('POST', '/store-api/checkout/order');

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);
        $order = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('purchasePrices', $order['lineItems'][0]['payload']);

        $criteria = new Criteria([$order['id']]);
        $criteria->addAssociation('lineItems');

        $this->browser->request(
            'POST',
            '/store-api/order',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            \json_encode(static::getContainer()->get(RequestCriteriaBuilder::class)->toArray($criteria), \JSON_THROW_ON_ERROR) ?: ''
        );

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);
        $response = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('purchasePrices', $response['orders']['elements'][0]['lineItems'][0]['payload']);
    }

    public function testOrderWithComment(): void
    {
        $this->createUserAndLogin();
        $this->addBlogToCart();

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order',
                [
                    'userComment' => '  test comment  ',
                ]
            );

        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('order', $response['apiAlias']);
        static::assertSame('test comment', $response['userComment']);
    }

    public function testOrderWithAffiliateAndCampaignTracking(): void
    {
        $this->createUserAndLogin();
        $this->addBlogToCart();

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order',
                [
                    'affiliateCode' => 'test affiliate code',
                    'campaignCode' => 'test campaign code',
                ]
            );

        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('order', $response['apiAlias']);
        static::assertSame('test affiliate code', $response['affiliateCode']);
        static::assertSame('test campaign code', $response['campaignCode']);
    }

    public function testOrderWithAffiliateTrackingOnly(): void
    {
        $this->createUserAndLogin();
        $this->addBlogToCart();

        // Order
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order',
                [
                    'affiliateCode' => 'test affiliate code',
                ]
            );

        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('order', $response['apiAlias']);
        static::assertSame('test affiliate code', $response['affiliateCode']);
        static::assertNull($response['campaignCode']);
    }

    public function testOrderWithCampaignTrackingOnly(): void
    {
        $this->createUserAndLogin();
        $this->addBlogToCart();

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order',
                [
                    'campaignCode' => 'test campaign code',
                ]
            );

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);
        $response = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('order', $response['apiAlias']);
        static::assertNull($response['affiliateCode']);
        static::assertSame('test campaign code', $response['campaignCode']);
    }

    public function testContextTokenExpiring(): void
    {
        /**
         * - login
         * - add blog p1
         * - simulate context token expiring
         * - check for new context token
         * - cart is empty
         * - add blog p2
         * - login
         * - check for new context token
         * - cart should contain both blogs
         */
        $connection = static::getContainer()->get(Connection::class);
        $this->blogRepository->create([
            [
                'id' => $this->ids->create('p2'),
                'blogNumber' => $this->ids->get('p2'),
                'stock' => 10,
                'name' => 'Test p2',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturerId' => $this->ids->get('manufacturerId'),
                'taxId' => $this->ids->get('tax'),
                'active' => true,
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());

        $email = Uuid::randomHex() . '@example.com';
        $password = 'contena';
        $originalToken = $this->createUserAndLogin($email, $password);

        $this->addBlogToCart();

        $interval = new \DateInterval(static::getContainer()->getParameter('contena.api.store.context_lifetime'));
        $intervalInSeconds = (new \DateTime())->setTimestamp(0)->add($interval)->getTimestamp();
        $intervalInDays = $intervalInSeconds / 86400 + 1;

        // expire $originalToken context
        $connection->executeStatement(
            '
            UPDATE channel_api_context
            SET updated_at = DATE_ADD(updated_at, INTERVAL :intervalInDays DAY)',
            ['intervalInDays' => -$intervalInDays]
        );

        $this->browser->request('GET', '/store-api/checkout/cart');

        $response = $this->browser->getResponse();
        $guestToken = $this->browser->getRequest()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertNotNull($guestToken);
        self::assertImplicitContextTokenHeader($response);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $guestToken);

        static::assertNotFalse($response->getContent());

        $data = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(0, $data['lineItems']);

        $response = $this->addBlogToCart('p2');
        $token = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertIsString($token);
        static::assertNotSame('', $token);
        $guestToken = $token;
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $guestToken);

        // the cart should be merged on login and a new token should be created
        $mergedToken = $this->login($email, $password);

        $this->browser->request('GET', '/store-api/checkout/cart');

        $response = $this->browser->getResponse();
        self::assertImplicitContextTokenHeader($response, $mergedToken);

        static::assertNotFalse($response->getContent());

        $data = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(2, $data['lineItems']);

        static::assertNotSame($guestToken, $mergedToken);
        static::assertNotSame($originalToken, $mergedToken);
    }

    public function testOrderPlacedCriteriaEventFired(): void
    {
        $this->createUserAndLogin();
        $this->addBlogToCart();

        $event = null;
        $this->catchEvent(CheckoutOrderPlacedCriteriaEvent::class, $event);

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order'
            );

        static::assertInstanceOf(CheckoutOrderPlacedCriteriaEvent::class, $event);
    }

    public function testPreparedPaymentStructForwarded(): void
    {
        $this->createUserAndLogin();
        $this->addBlogToCart();

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order'
            );

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

        /** @var EntityRepository<OrderTransactionCollection> $transactionRepo */
        $transactionRepo = static::getContainer()->get('order_transaction.repository');
        $transaction = $transactionRepo->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($transaction);
        static::assertContains('testValue', $transaction->getValidationData());
    }

    public function testTaxProviderAppliedIfGiven(): void
    {
        $taxProvider = [
            'id' => $this->ids->get('tax-provider'),
            'active' => true,
            'priority' => 1,
            'identifier' => TestConstantTaxRateProvider::class, // 7% tax rate
            'availabilityRule' => [
                'id' => $this->ids->get('rule'),
                'name' => 'test',
                'priority' => 1,
                'conditions' => [
                    ['type' => (new AlwaysValidRule())->getName()],
                ],
            ],
        ];

        $this->taxProviderRepository->create([$taxProvider], Context::createDefaultContext());
        $this->createUserAndLogin();
        $this->addBlogToCart();

        $this->browser
            ->request(
                Request::METHOD_POST,
                '/store-api/checkout/order'
            );

        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotCount(0, $response);
        static::assertArrayHasKey('price', $response);

        $price = $response['price'];

        static::assertArrayHasKey('netPrice', $price);
        static::assertArrayHasKey('totalPrice', $price);
        static::assertArrayHasKey('calculatedTaxes', $price);

        static::assertSame(9.3, $price['netPrice']);
        static::assertSame(10, $price['totalPrice']);
        static::assertCount(1, $price['calculatedTaxes']);

        $tax = $price['calculatedTaxes'][0];

        static::assertArrayHasKey('tax', $tax);
        static::assertArrayHasKey('taxRate', $tax);
        static::assertArrayHasKey('price', $tax);

        static::assertSame(0.7, $tax['tax']);
        static::assertSame(7, $tax['taxRate']);
        static::assertSame(10, $tax['price']);
    }

    public function testOrderWithExistingNotSpecifiedSalutation(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'contena';

        $this->createUserAndLogin($email, $password, true);
        $this->addBlogToCart();

        // Order
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order',
            );

        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotNull($response['orderUser']);
        static::assertNotNull($response['orderUser']['salutation']);
        static::assertSame($response['orderUser']['salutation']['salutationKey'], SalutationDefinition::NOT_SPECIFIED);
    }

    public function testOrderToNotSpecifiedWithoutExistingSalutation(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $email = Uuid::randomHex() . '@example.com';
        $password = 'contena';

        $connection->executeStatement(
            '
					DELETE FROM salutation WHERE salutation_key = :salutationKey
				',
            ['salutationKey' => SalutationDefinition::NOT_SPECIFIED]
        );

        $salutations = $connection->fetchAllKeyValue('SELECT salutation_key, id FROM salutation');
        static::assertArrayNotHasKey(SalutationDefinition::NOT_SPECIFIED, $salutations);

        $this->createUserAndLogin($email, $password, true);
        $this->addBlogToCart();

        // Order
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order',
            );

        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotNull($response['orderUser']);
        static::assertNull($response['orderUser']['salutationId']);
    }

    public function testBusinessUserNeedsVatIdWhenBillingCountryRequiresOne(): void
    {
        static::getContainer()->get('country.repository')->update([
            ['id' => $this->validCountryId, 'vatIdRequired' => true],
        ], Context::createDefaultContext());

        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser('contena', $email);
        $this->userRepository->update([
            ['id' => $userId, 'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS, 'company' => 'Example Inc.'],
        ], Context::createDefaultContext());

        $this->login($email, 'contena');
        $this->addBlogToCart();

        $this->browser->request('POST', '/store-api/checkout/order');

        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(CartException::CART_INVALID_CODE, $response['errors'][0]['code']);
        static::assertStringContainsString('vat-id-missing', $response['errors'][0]['detail']);

        $this->userRepository->update([
            ['id' => $userId, 'vatIds' => ['DE123456789']],
        ], Context::createDefaultContext());

        $this->browser->request('POST', '/store-api/checkout/order');

        static::assertNotFalse($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('order', $response['apiAlias']);
    }

    public function testOrderLockedWhenAlreadyInProgress(): void
    {
        $token = $this->createUserAndLogin();
        $response = $this->addBlogToCart();
        static::assertSame($token, $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        // Manually acquire lock to simulate concurrent request
        $cartLocker = $this->getContainer()->get(CartLocker::class);
        $lockKey = $cartLocker->getLockKey($token);
        $lock = $this->getContainer()->get('lock.factory')->createLock($lockKey, 5);
        $lock->acquire();

        // Try to create order while lock is held
        try {
            $this->browser
                ->request(
                    'POST',
                    '/store-api/checkout/order'
                );

            static::assertSame(409, $this->browser->getResponse()->getStatusCode());
            static::assertNotFalse($this->browser->getResponse()->getContent());
            $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            static::assertArrayHasKey('errors', $response);
            static::assertSame('CHECKOUT__CART_LOCKED', $response['errors'][0]['code']);
        } finally {
            // Release lock after test
            $lock->release();
        }
    }

    public function testOrderIsRejectedWhenTheCartWasAlreadyOrdered(): void
    {
        $token = $this->createUserAndLogin();
        $this->addBlogToCart();

        $context = static::getContainer()->get(ChannelContextService::class)
            ->get(new ChannelContextServiceParameters($this->ids->get('sales-channel'), $token));

        $cartService = static::getContainer()->get(CartService::class);

        // both requests read the cart before either of them places an order
        $cart = $cartService->getCart($token, $context, caching: false);
        $staleCart = $cartService->getCart($token, $context, caching: false);

        $cartService->order($cart, $context, new RequestDataBag());

        $this->expectExceptionObject(CartException::tokenNotFound($token));

        $cartService->order($staleCart, $context, new RequestDataBag());
    }

    protected function catchEvent(string $eventName, ?Event &$eventResult): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on($eventName, static function (Event $event) use (&$eventResult): void {
            $eventResult = $event;
        });
    }

    private function createTestData(): void
    {
        $this->addCountriesToChannel();

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
    }

    private function createUserAndLogin(
        ?string $email = null,
        ?string $password = null,
        bool $invalidSalutationId = false
    ): string {
        $email ??= Uuid::randomHex() . '@example.com';
        $password ??= 'contena';
        $this->createUser(
            $password,
            $email,
            $invalidSalutationId,
            $this->validSalutationId,
            $this->validCountryId
        );

        return $this->login($email, $password);
    }

    private function login(?string $email = null, ?string $password = null): string
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        return $contextToken;
    }

    private function createUser(
        string $password,
        ?string $email = null,
        bool $invalidSalutaionId = false,
        ?string $validSalutationId = null,
        ?string $validCountryId = null
    ): string {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->userRepository->create([
            [
                'id' => $userId,
                'channelId' => $this->ids->get('sales-channel'),
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $validSalutationId ?? $this->getValidSalutationId(),
                    'countryId' => $validCountryId ?? $this->getValidCountryId($this->ids->get('sales-channel')),
                ],
                'defaultBillingAddressId' => $addressId,
                'lastPaymentMethod' => [
                    'name' => 'Invoice',
                    'technicalName' => Uuid::randomHex(),
                    'active' => true,
                    'description' => 'Default payment method',
                    'handlerIdentifier' => TestPaymentHandler::class,
                    'channels' => [
                        [
                            'id' => $this->ids->get('sales-channel'),
                        ],
                    ],
                ],
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => ($invalidSalutaionId ? null : $validSalutationId ?? $this->getValidSalutationId()),
                'userNumber' => '12345',
            ],
        ], Context::createDefaultContext());

        return $userId;
    }

    private function addBlogToCart(string $id = 'p1'): Response
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get($id),
                            'type' => 'blog',
                            'referencedId' => $this->ids->get($id),
                        ],
                    ],
                ]
            );

        $response = $this->browser->getResponse();
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);
        $content = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $content['apiAlias']);
        static::assertSame(10, $content['price']['totalPrice']);
        static::assertCount(1, $content['lineItems']);

        return $response;
    }

    private static function assertImplicitContextTokenHeader(Response $response, ?string $contextToken = null): void
    {
        if (Feature::isActive('CACHE_REWORK')) {
            static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));

            return;
        }

        if ($contextToken === null) {
            static::assertIsString($response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
            static::assertNotSame('', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

            return;
        }

        static::assertSame($contextToken, $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }
}
