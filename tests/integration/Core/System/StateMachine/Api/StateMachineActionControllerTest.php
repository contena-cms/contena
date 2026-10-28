<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\StateMachine\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\PriceDefinitionFactory;
use Contena\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Contena\Core\Checkout\Cart\SalesChannel\CartService;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Order\OrderStates;
use Contena\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextService;
use Contena\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Contena\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryEntity;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Contena\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
class StateMachineActionControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use CountryAddToSalesChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    /**
     * @var EntityRepository<StateMachineHistoryCollection>
     */
    private EntityRepository $stateMachineHistoryRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->customerRepository = static::getContainer()->get('customer.repository');
        $this->stateMachineHistoryRepository = static::getContainer()->get('state_machine_history.repository');
    }

    public function testOrderNotFoundException(): void
    {
        $this->getBrowser()->request('GET', '/api/order/' . Uuid::randomHex() . '/actions/state');

        $response = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($response);
        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_NOT_FOUND, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);
    }

    public function testGetAvailableStates(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);

        $this->getBrowser()->request('GET', '/api/_action/state-machine/order/' . $orderId . '/state');

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());
        $response = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($response);
        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(2, $response['transitions']);
        static::assertSame('cancel', $response['transitions'][0]['actionName']);
        static::assertStringEndsWith('/_action/state-machine/order/' . $orderId . '/state/cancel', $response['transitions'][0]['url']);
    }

    public function testTransitionToAllowedState(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);

        $this->getBrowser()->request('GET', '/api/_action/state-machine/order/' . $orderId . '/state');

        $response = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($response);
        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        $actionUrl = $response['transitions'][0]['url'];
        $transitionTechnicalName = $response['transitions'][0]['technicalName'];

        $this->getBrowser()->request('POST', $actionUrl);

        $responseString = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($responseString);
        $response = json_decode($responseString, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            Response::HTTP_OK,
            $this->getBrowser()->getResponse()->getStatusCode(),
            $responseString
        );

        $stateId = $response['data']['id'] ?? '';
        static::assertTrue(Uuid::isValid($stateId));

        $destinationStateTechnicalName = $response['data']['attributes']['technicalName'];
        static::assertSame($transitionTechnicalName, $destinationStateTechnicalName);

        // test whether the state history was written
        $criteria = new Criteria();
        $criteria->addAssociation('fromStateMachineState');
        $criteria->addAssociation('toStateMachineState');

        $history = $this->stateMachineHistoryRepository->search($criteria, $context)->getEntities();

        static::assertCount(1, $history->getElements(), 'Expected history to be written');
        /** @var StateMachineHistoryEntity $historyEntry */
        $historyEntry = array_values($history->getElements())[0];

        $toStateMachineState = $historyEntry->getToStateMachineState();
        static::assertInstanceOf(StateMachineStateEntity::class, $toStateMachineState);
        static::assertSame($destinationStateTechnicalName, $toStateMachineState->getTechnicalName());

        static::assertSame(static::getContainer()->get(OrderDefinition::class)->getEntityName(), $historyEntry->getEntityName());

        static::assertSame($orderId, $historyEntry->getReferencedId());
        static::assertSame(Defaults::LIVE_VERSION, $historyEntry->getReferencedVersionId());
    }

    public function testTransitionToNotAllowedState(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);

        $this->getBrowser()->request('POST', '/api/_action/state-machine/order/' . $orderId . '/state/foo');

        $response = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($response);
        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);
    }

    public function testOrderCartDe(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);

        $cartService = static::getContainer()->get(CartService::class);

        $options = [
            SalesChannelContextService::LANGUAGE_ID => $this->getDeDeLanguageId(),
            SalesChannelContextService::CUSTOMER_ID => $customerId,
            SalesChannelContextService::SHIPPING_METHOD_ID => $this->createShippingMethod(),
        ];

        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, $options);

        $productId = Uuid::randomHex();
        $product = [
            'id' => $productId,
            'productNumber' => $productId,
            'name' => [
                'de-DE' => 'test',
                'en-GB' => 'test',
            ],
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => $salesChannelContext->getSalesChannelId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false],
            ],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 18],
            'manufacturer' => [
                'name' => [
                    'de-DE' => 'test',
                    'en-GB' => 'test',
                ],
            ],
        ];

        static::getContainer()->get('product.repository')
            ->create([$product], $salesChannelContext->getContext());
        $this->addTaxDataToSalesChannel($salesChannelContext, $product['tax']);

        $lineItem = (new ProductLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $productId, 'referencedId' => $productId], $salesChannelContext);

        $cart = $cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        $cart = $cartService->add($cart, $lineItem, $salesChannelContext);

        static::assertTrue($cart->has($productId));

        $orderId = $cartService->order($cart, $salesChannelContext, new RequestDataBag());

        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');

        /** @var OrderEntity $order */
        $order = $orderRepository->search(new Criteria([$orderId]), $salesChannelContext->getContext())->getEntities()->first();

        static::assertSame($order->getLanguageId(), $this->getDeDeLanguageId());
    }

    public function testOrderCartEn(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);

        $cartService = static::getContainer()->get(CartService::class);

        $options = [
            SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
            SalesChannelContextService::CUSTOMER_ID => $customerId,
            SalesChannelContextService::SHIPPING_METHOD_ID => $this->createShippingMethod(),
        ];

        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, $options);

        $productId = Uuid::randomHex();
        $product = [
            'id' => $productId,
            'productNumber' => $productId,
            'name' => [
                'de-DE' => 'test',
                'en-GB' => 'test',
            ],
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false],
            ],
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => $salesChannelContext->getSalesChannelId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 18],
            'manufacturer' => [
                'name' => [
                    'de-DE' => 'test',
                    'en-GB' => 'test',
                ],
            ],
        ];

        static::getContainer()->get('product.repository')
            ->create([$product], $salesChannelContext->getContext());
        $this->addTaxDataToSalesChannel($salesChannelContext, $product['tax']);

        $lineItem = (new ProductLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $productId, 'referencedId' => $productId], $salesChannelContext);

        $cart = $cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        $cart = $cartService->add($cart, $lineItem, $salesChannelContext);

        static::assertTrue($cart->has($productId));

        $orderId = $cartService->order($cart, $salesChannelContext, new RequestDataBag());

        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');

        /** @var OrderEntity $order */
        $order = $orderRepository->search(new Criteria([$orderId]), $salesChannelContext->getContext())->getEntities()->first();

        static::assertSame($order->getLanguageId(), Defaults::LANGUAGE_SYSTEM);
    }

    private function createShippingMethod(): string
    {
        $rule = [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'priority' => 1,
            'conditions' => [
                ['type' => (new AlwaysValidRule())->getName()],
            ],
        ];

        static::getContainer()->get('rule.repository')
            ->create([$rule], Context::createDefaultContext());

        $shipping = [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'technicalName' => 'shipping_test',
            'active' => true,
            'prices' => [
                [
                    'ruleId' => null,
                    'quantityStart' => 0,
                    'currencyPrice' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false],
                    ],
                ],
            ],
            'availabilityRuleId' => $rule['id'],
            'deliveryTimeId' => static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROm delivery_time LIMIT 1'),
            'salesChannels' => [['id' => TestDefaults::SALES_CHANNEL]],
        ];

        static::getContainer()->get('shipping_method.repository')
            ->create([$shipping], Context::createDefaultContext());

        return $shipping['id'];
    }

    private function createOrder(string $customerId, Context $context): string
    {
        $orderId = Uuid::randomHex();
        $stateId = static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE);
        $billingAddressId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderNumber' => Uuid::randomHex(),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $stateId,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'billingAddressId' => $billingAddressId,
            'addresses' => [
                [
                    'id' => $billingAddressId,
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ];

        $this->orderRepository->upsert([$order], $context);

        return $orderId;
    }

    private function createCustomer(Context $context): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $this->addCountriesToSalesChannel();

        $customer = [
            'id' => $customerId,
            'customerNumber' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->customerRepository->upsert([$customer], $context);

        return $customerId;
    }
}
