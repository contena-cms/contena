<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Shipping\Channel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Contena\Core\Checkout\Cart\Rule\CartAmountRule;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Contena\Core\Checkout\Shipping\Hook\ShippingMethodRouteHook;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\DeliveryTime\DeliveryTimeEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('store-api')]
class ShippingMethodRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'shippingMethodId' => $this->ids->get('shipping'),
            'shippingMethods' => [
                ['id' => $this->ids->get('shipping')],
                ['id' => $this->ids->get('shipping2')],
                ['id' => $this->ids->get('shipping3')],
            ],
        ]);
    }

    public function testLoad(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/shipping-method',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        $ids = array_column($response['elements'], 'id');

        static::assertSame(3, $response['total']);
        static::assertContains($this->ids->get('shipping'), $ids);
        static::assertContains($this->ids->get('shipping2'), $ids);
        static::assertNull($response['elements'][0]['availabilityRule']);

        $traces = $this->browser->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(ShippingMethodRouteHook::HOOK_NAME, $traces);
    }

    public function testSortOrderWithDefault(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/shipping-method',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        $ids = array_column($response['elements'], 'id');

        static::assertSame(
            [
                $this->ids->get('shipping'),    // position  1 (selected method & sales-channel default)
                $this->ids->get('shipping3'),   // position -3
                $this->ids->get('shipping2'),   // position  5
            ],
            $ids
        );
    }

    public function testSortOrderWithSelectedShippingMethod(): void
    {
        $this->browser->request(
            'PATCH',
            '/store-api/context',
            ['shippingMethodId' => $this->ids->get('shipping2')]
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/shipping-method',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        $ids = array_column($response['elements'], 'id');

        static::assertSame(
            [
                $this->ids->get('shipping'),    // position  1 (sales-channel default)
                $this->ids->get('shipping3'),   // position -3
                $this->ids->get('shipping2'),   // position  5 (selected method)
            ],
            $ids
        );
    }

    public function testOnlyAvailableExcludesShippingMethodsWithoutAnyPrice(): void
    {
        static::getContainer()->get('shipping_method.repository')->update([[
            'id' => $this->ids->get('shipping'),
            'prices' => [
                [
                    'id' => $this->ids->create('price'),
                    'calculation' => 1,
                    'quantityStart' => 1,
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 10,
                            'gross' => 11,
                            'linked' => false,
                        ],
                    ],
                ],
                // A nullable field on one row must not turn the existence check into an anti-join
                [
                    'id' => $this->ids->create('price-without-currency-price'),
                    'calculation' => 1,
                    'quantityStart' => 2,
                ],
            ],
        ]], Context::createDefaultContext());

        $this->browser->request('POST', '/store-api/shipping-method', ['onlyAvailable' => true]);

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        static::assertSame([$this->ids->get('shipping')], array_column($response['elements'], 'id'));

        $this->browser->request('POST', '/store-api/shipping-method', []);

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        static::assertSame(3, $response['total']);
    }

    public function testOnlyAvailableExcludesShippingMethodsWhoseOnlyPricesHaveNoCurrencyValues(): void
    {
        static::getContainer()->get('shipping_method.repository')->update([[
            'id' => $this->ids->get('shipping'),
            'prices' => [
                ['id' => $this->ids->create('empty1'), 'calculation' => 1, 'quantityStart' => 1],
                ['id' => $this->ids->create('empty2'), 'calculation' => 1, 'quantityStart' => 2],
            ],
        ]], Context::createDefaultContext());

        $this->browser->request('POST', '/store-api/shipping-method', ['onlyAvailable' => true]);

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        static::assertSame([], array_column($response['elements'], 'id'));
    }

    public function testIncludes(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/shipping-method',
                [
                    'includes' => [
                        'shipping_method' => [
                            'name',
                        ],
                    ],
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        static::assertSame(3, $response['total']);
        static::assertArrayHasKey('name', $response['elements'][0]);
        static::assertArrayNotHasKey('id', $response['elements'][0]);
    }

    public function testAssociations(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/shipping-method',
                [
                    'associations' => [
                        'availabilityRule' => [],
                    ],
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR) ?: [];

        static::assertSame(3, $response['total']);
        static::assertIsArray($response['elements'][0]['availabilityRule']);
        static::assertNotCount(0, $response['elements'][0]['availabilityRule']);
    }

    public static function httpMethodProvider(): \Generator
    {
        yield 'GET with query parameters' => ['GET'];
        yield 'POST with a JSON body' => ['POST'];
    }

    #[DataProvider('httpMethodProvider')]
    public function testOnlyAvailableIsEvaluatedForTheSessionWithoutOrderId(string $httpMethod): void
    {
        $this->createRules();
        $this->createShippingMethod('order-shipping', availabilityRuleId: $this->ids->get('order-rule'));
        $this->createShippingMethod('session-shipping', availabilityRuleId: $this->ids->get('session-rule'));
        $this->login($this->browser);

        $shippingMethodIds = $this->loadShippingMethodIds($httpMethod, ['onlyAvailable' => true]);

        static::assertNotContains($this->ids->get('order-shipping'), $shippingMethodIds);
        static::assertContains($this->ids->get('session-shipping'), $shippingMethodIds);
    }

    #[DataProvider('httpMethodProvider')]
    public function testOnlyAvailableWithOrderIdUsesTheRulesStoredOnTheOrder(string $httpMethod): void
    {
        $this->createRules();
        $this->createShippingMethod('order-shipping', availabilityRuleId: $this->ids->get('order-rule'));
        $this->createShippingMethod('session-shipping', availabilityRuleId: $this->ids->get('session-rule'));
        $orderId = $this->createOrder($this->login($this->browser), ruleIds: [$this->ids->get('order-rule')]);

        $shippingMethodIds = $this->loadShippingMethodIds($httpMethod, ['onlyAvailable' => true, 'orderId' => $orderId]);

        static::assertContains($this->ids->get('order-shipping'), $shippingMethodIds);
        static::assertNotContains($this->ids->get('session-shipping'), $shippingMethodIds);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return list<string>
     */
    private function loadShippingMethodIds(string $httpMethod, array $parameters): array
    {
        if ($httpMethod === 'GET') {
            $this->browser->request('GET', '/store-api/shipping-method?' . http_build_query($parameters));
        } else {
            $this->browser->request(
                'POST',
                '/store-api/shipping-method',
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode($parameters, \JSON_THROW_ON_ERROR),
            );
        }

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return array_column($content['elements'], 'id');
    }

    private function createRules(): void
    {
        static::getContainer()->get('rule.repository')->create([
            [
                'id' => $this->ids->create('order-rule'),
                'name' => 'Cart amount the session cart never reaches',
                'priority' => 1,
                'conditions' => [[
                    'type' => CartAmountRule::RULE_NAME,
                    'value' => ['operator' => Rule::OPERATOR_GTE, 'amount' => 1_000_000],
                ]],
            ],
            [
                'id' => $this->ids->create('session-rule'),
                'name' => 'Always valid',
                'priority' => 1,
                'conditions' => [['type' => AlwaysValidRule::RULE_NAME]],
            ],
        ], Context::createDefaultContext());
    }

    private function createShippingMethod(string $key, string $availabilityRuleId): void
    {
        static::getContainer()->get('shipping_method.repository')->create([[
            'id' => $this->ids->create($key),
            'name' => $key,
            'technicalName' => 'shipping_' . $key,
            'active' => true,
            'availabilityRuleId' => $availabilityRuleId,
            'deliveryTime' => [
                'name' => 'testDeliveryTime',
                'min' => 1,
                'max' => 90,
                'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            ],
            'prices' => [[
                'calculation' => 1,
                'quantityStart' => 1,
                'currencyPrice' => [[
                    'currencyId' => Defaults::CURRENCY,
                    'net' => 10,
                    'gross' => 11,
                    'linked' => false,
                ]],
            ]],
            'channels' => [['id' => $this->ids->get('sales-channel')]],
        ]], Context::createDefaultContext());
    }

    /**
     * @param list<string> $ruleIds
     */
    private function createOrder(string $userId, array $ruleIds = []): string
    {
        $orderId = Uuid::randomHex();
        $billingAddressId = Uuid::randomHex();
        $price = new CalculatedPrice(unitPrice: 10, totalPrice: 10, calculatedTaxes: new CalculatedTaxCollection(), taxRules: new TaxRuleCollection());
        $rounding = ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true];

        static::getContainer()->get('order.repository')->create([[
            'id' => $orderId,
            'orderNumber' => '10000',
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'channelId' => $this->ids->get('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'stateId' => $this->getStateMachineState(),
            'ruleIds' => $ruleIds,
            'price' => new CartPrice(
                netPrice: 10,
                totalPrice: 10,
                positionPrice: 10,
                calculatedTaxes: new CalculatedTaxCollection(),
                taxRules: new TaxRuleCollection(),
                taxStatus: CartPrice::TAX_STATE_NET,
            ),
            'shippingCosts' => new CalculatedPrice(unitPrice: 0, totalPrice: 0, calculatedTaxes: new CalculatedTaxCollection(), taxRules: new TaxRuleCollection()),
            'itemRounding' => $rounding,
            'totalRounding' => $rounding,
            'orderUser' => [
                'userId' => $userId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'billingAddressId' => $billingAddressId,
            'addresses' => [[
                'id' => $billingAddressId,
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schöppingen',
                'countryId' => $this->getValidCountryId(),
            ]],
            'lineItems' => [[
                'identifier' => 'custom-item',
                'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
                'label' => 'Custom item',
                'quantity' => 1,
                'price' => $price,
                'priceDefinition' => new QuantityPriceDefinition(price: 10, taxRules: new TaxRuleCollection()),
            ]],
            'deliveries' => [],
            'transactions' => [[
                'paymentMethodId' => $this->getAvailablePaymentMethod()->getId(),
                'stateId' => $this->getStateMachineState(OrderTransactionStates::STATE_MACHINE, OrderTransactionStates::STATE_OPEN),
                'amount' => $price,
            ]],
        ]], Context::createDefaultContext());

        return $orderId;
    }

    private function createData(): void
    {
        $data = [
            [
                'id' => $this->ids->create('shipping'),
                'active' => true,
                'position' => 1,
                'bindShippingfree' => false,
                'name' => 'test',
                'technicalName' => 'shipping_test',
                'availabilityRule' => [
                    'id' => $this->ids->create('rule'),
                    'name' => 'asd',
                    'priority' => 2,
                    'conditions' => [
                        [
                            'type' => 'dateRange',
                            'value' => [
                                'fromDate' => '2000-06-07T11:37:51',
                                'toDate' => '2099-06-07T11:37:51',
                                'useTime' => false,
                            ],
                        ],
                    ],
                ],
                'deliveryTime' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'testDeliveryTime',
                    'min' => 1,
                    'max' => 90,
                    'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
                ],
            ],
            [
                'id' => $this->ids->create('shipping2'),
                'active' => true,
                'position' => 5,
                'bindShippingfree' => false,
                'name' => 'test',
                'technicalName' => 'shipping_test2',
                'availabilityRule' => [
                    'id' => $this->ids->create('rule2'),
                    'name' => 'asd',
                    'priority' => 2,
                    'conditions' => [
                        [
                            'type' => 'dateRange',
                            'value' => [
                                'fromDate' => '2000-06-07T11:37:51',
                                'toDate' => '2099-06-07T11:37:51',
                                'useTime' => false,
                            ],
                        ],
                    ],
                ],
                'deliveryTime' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'testDeliveryTime',
                    'min' => 1,
                    'max' => 90,
                    'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
                ],
            ],
            [
                'id' => $this->ids->create('shipping3'),
                'active' => true,
                'position' => -3,
                'bindShippingfree' => false,
                'name' => 'test',
                'technicalName' => 'shipping_test3',
                'availabilityRule' => [
                    'id' => $this->ids->create('rule3'),
                    'name' => 'asd',
                    'priority' => 2,
                    'conditions' => [
                        [
                            'type' => 'dateRange',
                            'value' => [
                                'fromDate' => '2000-06-07T11:37:51',
                                'toDate' => '2000-06-07T11:37:51',
                                'useTime' => false,
                            ],
                        ],
                    ],
                ],
                'deliveryTime' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'testDeliveryTime',
                    'min' => 1,
                    'max' => 90,
                    'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
                ],
            ],
        ];

        static::getContainer()->get('shipping_method.repository')
            ->create($data, Context::createDefaultContext());
    }
}
