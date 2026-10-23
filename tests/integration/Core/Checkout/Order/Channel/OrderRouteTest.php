<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Order\Channel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Checkout\Document\FileGenerator\FileTypes;
use Contena\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Contena\Core\Checkout\Document\Service\DocumentGenerator;
use Contena\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Contena\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderException;
use Contena\Core\Checkout\Order\OrderStates;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Util\Base64;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionIntegrationTestBehaviour;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Controller\AccountOrderController;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('store-api')]
class OrderRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;
    use PromotionIntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private string $orderId;

    private ChannelContextPersister $contextPersister;

    private RequestCriteriaBuilder $requestCriteriaBuilder;

    private string $userId;

    private string $email;

    private string $defaultPaymentMethodId;

    private string $defaultCountryId;

    private string $deepLinkCode;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->defaultCountryId = $this->getValidCountryId(null);

        $validCountries = $this->getValidCountries()->getElements();
        $this->browser = $this->createCustomChannelBrowser([
            'id' => TestDefaults::CHANNEL,
            'languages' => [],
            'countryId' => $this->defaultCountryId,
            'countries' => \array_map(static fn (CountryEntity $country) => ['id' => $country->getId()], $validCountries),
        ]);

        $this->assignChannelContext($this->browser);

        $this->contextPersister = static::getContainer()->get(ChannelContextPersister::class);
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->requestCriteriaBuilder = static::getContainer()->get(RequestCriteriaBuilder::class);
        $this->email = Uuid::randomHex() . '@example.com';
        $this->userId = Uuid::randomHex();
        $firstPaymentMethod = $this->getValidPaymentMethods()->first();
        static::assertNotNull($firstPaymentMethod);
        $this->defaultPaymentMethodId = $firstPaymentMethod->getId();
        $this->orderId = $this->createOrder($this->userId, $this->email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'email' => $this->email,
                    'password' => 'contena',
                ], \JSON_THROW_ON_ERROR) ?: ''
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $channelContext = $channelContextFactory->create($contextToken, TestDefaults::CHANNEL);

        $newToken = $this->contextPersister->replace($contextToken, $channelContext);
        $this->contextPersister->save(
            $newToken,
            [
                'userId' => $this->userId,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            TestDefaults::CHANNEL,
            $this->userId
        );

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $newToken);
    }

    public function testGetOrder(): void
    {
        $criteria = new Criteria([$this->orderId]);

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                $this->requestCriteriaBuilder->toArray($criteria)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey(0, $response['orders']['elements']);
        static::assertArrayHasKey('id', $response['orders']['elements'][0]);
        static::assertSame($this->orderId, $response['orders']['elements'][0]['id']);
    }

    public function testGetOrderGuest(): void
    {
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', '');

        $criteria = new Criteria([$this->orderId]);
        $criteria->addAssociation('orderUser');

        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->getEntities()->get($this->orderId);

        static::assertNotNull($order);
        static::assertNotNull($order->getOrderUser());

        $this->userRepository->update([
            [
                'id' => $order->getOrderUser()->getUserId(),
                'guest' => true,
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$this->orderId]);
        $criteria->addFilter(new EqualsFilter('deepLinkCode', $this->deepLinkCode));

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                \array_merge(
                    $this->requestCriteriaBuilder->toArray($criteria),
                    [
                        'email' => 'test@example.com',
                        'zipcode' => '59438-0403',
                    ]
                )
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey(0, $response['orders']['elements']);
        static::assertArrayHasKey('id', $response['orders']['elements'][0]);
        static::assertSame($this->orderId, $response['orders']['elements'][0]['id']);
    }

    public function testGetOrderGuestWrongDeepLink(): void
    {
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', '');

        $criteria = new Criteria([$this->orderId]);
        $criteria->addAssociation('orderUser');

        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->getEntities()->get($this->orderId);

        static::assertNotNull($order);
        static::assertNotNull($order->getOrderUser());

        $this->userRepository->update([
            [
                'id' => $order->getOrderUser()->getUserId(),
                'guest' => true,
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$this->orderId]);
        $criteria->addFilter(new EqualsFilter('deepLinkCode', Uuid::randomHex()));

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                \array_merge(
                    $this->requestCriteriaBuilder->toArray($criteria),
                    [
                        'email' => 'test@example.com',
                        'zipcode' => '59438-0403',
                    ]
                )
            );

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode());
    }

    #[DataProvider('guestCredentialsProvider')]
    public function testGuestCredentialsAreReadFromTheCompressedCriteria(string $zipcode, int $expectedStatus, ?string $expectedErrorCode): void
    {
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', '');

        $criteria = new Criteria([$this->orderId]);
        $criteria->addAssociation('orderUser');

        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->getEntities()->get($this->orderId);

        static::assertNotNull($order);
        static::assertNotNull($order->getOrderUser());

        $this->userRepository->update([
            [
                'id' => $order->getOrderUser()->getUserId(),
                'guest' => true,
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$this->orderId]);
        $criteria->addFilter(new EqualsFilter('deepLinkCode', $this->deepLinkCode));

        $compressed = gzencode(json_encode(
            \array_merge(
                $this->requestCriteriaBuilder->toArray($criteria),
                [
                    'email' => 'test@example.com',
                    'zipcode' => $zipcode,
                ]
            ),
            \JSON_THROW_ON_ERROR
        ));
        static::assertNotFalse($compressed, 'Gzip compressing failed');

        $this->browser->request('GET', '/store-api/order', ['_criteria' => Base64::urlEncode($compressed)]);

        $content = (string) $this->browser->getResponse()->getContent();
        static::assertSame($expectedStatus, $this->browser->getResponse()->getStatusCode(), $content);

        if ($expectedErrorCode !== null) {
            $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            static::assertSame($expectedErrorCode, $response['errors'][0]['code']);
        }
    }

    /**
     * @return iterable<string, array{zipcode: string, expectedStatus: int, expectedErrorCode: ?string}>
     */
    public static function guestCredentialsProvider(): iterable
    {
        yield 'the order is returned for the right credentials' => [
            'zipcode' => '59438-0403',
            'expectedStatus' => Response::HTTP_OK,
            'expectedErrorCode' => null,
        ];

        yield 'a wrong zipcode is checked and rejected the same as a plain query parameter' => [
            'zipcode' => '00000',
            'expectedStatus' => Response::HTTP_FORBIDDEN,
            'expectedErrorCode' => OrderException::CHECKOUT_GUEST_WRONG_CREDENTIALS,
        ];
    }

    public function testGetOrderGuestNoOrder(): void
    {
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', '');

        $criteria = new Criteria([Uuid::randomHex()]);
        $criteria->addFilter(new EqualsFilter('deepLinkCode', $this->deepLinkCode));

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                $this->requestCriteriaBuilder->toArray($criteria),
            );

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode());
    }

    public function testGetOrderShowsValidDocuments(): void
    {
        $this->createDocument($this->orderId);

        $criteria = new Criteria([$this->orderId]);

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                $this->requestCriteriaBuilder->toArray($criteria)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey('documents', $response['orders']['elements'][0]);
        static::assertCount(1, $response['orders']['elements'][0]['documents']);
    }

    public function testGetOrderDoesNotShowUnAvailableDocuments(): void
    {
        $this->createDocument($this->orderId, false);

        $criteria = new Criteria([$this->orderId]);

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                $this->requestCriteriaBuilder->toArray($criteria)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey('documents', $response['orders']['elements'][0]);
        static::assertCount(0, $response['orders']['elements'][0]['documents']);
    }

    public function testGetOrderDoesNotShowHasNotSentDocument(): void
    {
        $this->createDocument($this->orderId, true, false);

        $criteria = new Criteria([$this->orderId]);

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                $this->requestCriteriaBuilder->toArray($criteria)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey('documents', $response['orders']['elements'][0]);
        static::assertCount(0, $response['orders']['elements'][0]['documents']);
    }

    public function testGetOrderCheckPromotion(): void
    {
        $criteria = new Criteria([$this->orderId]);

        $this->browser
            ->request(
                'POST',
                '/store-api/order?checkPromotion=true',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($this->requestCriteriaBuilder->toArray($criteria), \JSON_THROW_ON_ERROR) ?: ''
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertArrayHasKey(0, $response['orders']['elements']);
        static::assertArrayHasKey('id', $response['orders']['elements'][0]);
        static::assertSame($this->orderId, $response['orders']['elements'][0]['id']);
        static::assertIsArray($response);
        static::assertArrayHasKey('paymentChangeable', $response);
        static::assertCount(1, $response['paymentChangeable']);
        static::assertTrue(array_pop($response['paymentChangeable']));
    }

    public function testSetPaymentOrder(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'orderId' => $this->orderId,
                    'paymentMethodId' => $this->defaultPaymentMethodId,
                ], \JSON_THROW_ON_ERROR) ?: ''
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('success', $response, print_r($response, true));
        static::assertTrue($response['success'], print_r($response, true));

        $criteria = new Criteria([$this->orderId]);
        $criteria->addAssociation('transactions');

        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->getEntities()->get($this->orderId);

        static::assertNotNull($order);
        static::assertNotNull($transactions = $order->getTransactions());
        static::assertNotNull($transaction = $transactions->last());
        static::assertSame($this->defaultPaymentMethodId, $transaction->getPaymentMethodId());
    }

    public function testSetAnotherPaymentMethodToOrder(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without frontend in NEXT-16882');
        }

        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $defaultPaymentMethodId = $this->defaultPaymentMethodId;
        $newPaymentMethod = $this->getValidPaymentMethods()->filter(static fn (PaymentMethodEntity $paymentMethod) => $paymentMethod->getId() !== $defaultPaymentMethodId)->first();
        $newPaymentMethodId = $newPaymentMethod?->getId() ?? '';

        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'orderId' => $this->orderId,
                    'paymentMethodId' => $newPaymentMethodId,
                ], \JSON_THROW_ON_ERROR) ?: ''
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('success', $response, print_r($response, true));
        static::assertTrue($response['success'], print_r($response, true));

        static::assertInstanceOf(MailSentEvent::class, $mail, 'The mail.sent Event did not run');
        $htmlText = $mail->getContents()['text/html'];
        static::assertIsString($htmlText);
        static::assertStringContainsString('the payment method for your order has been successfully changed', $htmlText);
    }

    public function testSetSamePaymentMethodToOrder(): void
    {
        $mail = null;
        $this->catchEvent(MailSentEvent::class, $mail);

        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'orderId' => $this->orderId,
                    'paymentMethodId' => $this->defaultPaymentMethodId,
                ], \JSON_THROW_ON_ERROR) ?: ''
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('success', $response, print_r($response, true));
        static::assertTrue($response['success'], print_r($response, true));

        static::assertNull($mail, 'Resubmitting the unchanged payment method must not notify the user');
    }

    public function testSetPaymentOrderWrongPayment(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                \json_encode([
                    'orderId' => $this->orderId,
                    'paymentMethodId' => Uuid::randomHex(),
                ], \JSON_THROW_ON_ERROR) ?: ''
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }

    public function testOrderChannelRestriction(): void
    {
        $testChannel = $this->createChannel([
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://foo.de',
                ],
            ],
        ]);

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $this->userId, $this->email);
        unset($orderData[0]['orderUser']['user']['password']);
        $this->orderRepository->create($orderData, Context::createDefaultContext());

        $this->orderRepository->update([
            [
                'id' => $orderId,
                'channelId' => $testChannel['id'],
            ],
        ], Context::createDefaultContext());

        $this->browser
            ->request(
                'GET',
                '/store-api/order',
                $this->requestCriteriaBuilder->toArray(new Criteria())
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('orders', $response);
        static::assertArrayHasKey('elements', $response['orders']);
        static::assertIsArray($response['orders']['elements']);
        static::assertArrayHasKey(0, $response['orders']['elements']);
        static::assertCount(1, $response['orders']['elements']);
        static::assertArrayHasKey('id', $response['orders']['elements'][0]);
        static::assertSame($this->orderId, $response['orders']['elements'][0]['id']);
        static::assertSame(TestDefaults::CHANNEL, $response['orders']['elements'][0]['channelId']);
    }

    protected function getValidPaymentMethods(): PaymentMethodCollection
    {
        /** @var EntityRepository<PaymentMethodCollection> $paymentMethodRepository */
        $paymentMethodRepository = static::getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('availabilityRuleId', null))
            ->addFilter(new EqualsFilter('active', true));

        return $paymentMethodRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }

    protected function getValidCountries(): CountryCollection
    {
        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('shippingAvailable', true));

        return $countryRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }

    private function createOrder(string $userId, string $email): string
    {
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $userId, $email);
        $this->orderRepository->create($orderData, Context::createDefaultContext());

        return $orderId;
    }

    /**
     * @return array<mixed>
     */
    private function getOrderData(string $orderId, string $userId, string $email): array
    {
        $addressId = Uuid::randomHex();
        $orderLineItemId = Uuid::randomHex();
        $transactionId = Uuid::randomHex();
        $salutation = $this->getValidSalutationId();

        $order = [
            [
                'id' => $orderId,
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'orderNumber' => Uuid::randomHex(),
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE),
                'paymentMethodId' => $this->defaultPaymentMethodId,
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1,
                'channelId' => TestDefaults::CHANNEL,
                'primaryOrderTransactionId' => $transactionId,
                'transactions' => [
                    [
                        'id' => $transactionId,
                        'paymentMethodId' => $this->defaultPaymentMethodId,
                        'amount' => [
                            'unitPrice' => 10.0,
                            'totalPrice' => 10.0,
                            'quantity' => 1,
                            'calculatedTaxes' => [],
                            'taxRules' => [],
                        ],
                        'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderTransactionStates::STATE_MACHINE),
                    ],
                ],
                'deliveries' => [
                    [
                        'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderDeliveryStates::STATE_MACHINE),
                        'shippingMethodId' => $this->getValidShippingMethodId(),
                        'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingDateEarliest' => date(\DATE_ATOM),
                        'shippingDateLatest' => date(\DATE_ATOM),
                        'shippingOrderAddress' => [
                            'salutationId' => $salutation,
                            'firstName' => 'Floy',
                            'lastName' => 'Glover',
                            'zipcode' => '59438-0403',
                            'city' => 'Stellaberg',
                            'street' => 'street',
                            'country' => [
                                'name' => 'kasachstan',
                                'id' => $this->getValidCountryId(),
                            ],
                        ],
                        'positions' => [
                            [
                                'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                                'orderLineItemId' => $orderLineItemId,
                            ],
                        ],
                    ],
                ],
                'lineItems' => [
                    [
                        'id' => $orderLineItemId,
                        'identifier' => 'test',
                        'quantity' => 1,
                        'type' => 'test',
                        'label' => 'test',
                        'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection()),
                        'good' => true,
                    ],
                ],
                'deepLinkCode' => $this->deepLinkCode = Uuid::randomHex(),
                'orderUser' => [
                    'email' => 'test@example.com',
                    'firstName' => 'Noe',
                    'lastName' => 'Hill',
                    'salutationId' => $salutation,
                    'title' => 'Doc',
                    'userNumber' => 'Test',
                    'user' => [
                        'id' => $userId,
                        'channelId' => TestDefaults::CHANNEL,
                        'defaultShippingAddress' => [
                            'id' => $addressId,
                            'firstName' => 'Max',
                            'lastName' => 'Mustermann',
                            'street' => 'Musterstraße 1',
                            'city' => 'Schoöppingen',
                            'zipcode' => '12345',
                            'salutationId' => $this->getValidSalutationId(),
                            'countryId' => $this->getValidCountryId(),
                        ],
                        'defaultBillingAddressId' => $addressId,
                        'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                        'email' => $email,
                        'password' => TestDefaults::HASHED_PASSWORD,
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                        'salutationId' => $this->getValidSalutationId(),
                        'userNumber' => '12345',
                    ],
                ],
                'billingAddressId' => $addressId,
                'addresses' => [
                    [
                        'salutationId' => $salutation,
                        'firstName' => 'Floy',
                        'lastName' => 'Glover',
                        'zipcode' => '59438-0403',
                        'city' => 'Stellaberg',
                        'street' => 'street',
                        'countryId' => $this->getValidCountryId(),
                        'id' => $addressId,
                    ],
                ],
            ],
        ];

        return $order;
    }

    private function createDocument(string $orderId, bool $showInUserAccount = true, bool $sent = true): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', DeliveryNoteRenderer::TYPE));

        $operation = new DocumentGenerateOperation(
            $orderId,
            FileTypes::PDF,
            ['documentNumber' => '1001', 'displayInUserAccount' => $showInUserAccount],
        );

        $document = static::getContainer()->get(DocumentGenerator::class)
            ->generate(DeliveryNoteRenderer::TYPE, [$orderId => $operation], Context::createDefaultContext())->getSuccess()->first();

        static::assertNotNull($document);

        static::getContainer()->get('document.repository')->update([
            [
                'id' => $document->getId(),
                'sent' => $sent,
            ],
        ], Context::createDefaultContext());
    }
}
