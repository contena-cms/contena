<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Order\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class CancelOrderRouteTest extends TestCase
{
    use UserTestTrait;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->assignChannelContext($this->browser);

        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email);

        $this->ids->set('order-1', $this->createOrder($this->ids, $userId));
        $this->ids->set('order-2', $this->createOrder($this->ids, $this->createUser('test-other@test.de')));

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'contena',
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->getContainer()->get(SystemConfigService::class)->set('core.cart.enableOrderRefunds', true);
    }

    public function testCancelMyOwnOrder(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/order/state/cancel',
                [
                    'orderId' => $this->ids->get('order-1'),
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertSame('cancelled', $response['technicalName']);

        $criteria = new Criteria([$this->ids->get('order-1')]);
        $criteria->addAssociation('stateMachineState');

        /** @var OrderEntity $order */
        $order = static::getContainer()->get('order.repository')->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($order->getStateMachineState());
        static::assertSame('cancelled', $order->getStateMachineState()->getTechnicalName());
    }

    public function testCancellingMyOwnOrderIsRecordedAsChannelStateChange(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/order/state/cancel',
                [
                    'orderId' => $this->ids->get('order-1'),
                ]
            );

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        $connection = static::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        $history = $connection->fetchAssociative(
            'SELECT source_type, admin_user_id, integration_id FROM `state_machine_history` WHERE referenced_id = :id ORDER BY created_at DESC LIMIT 1',
            ['id' => Uuid::fromHexToBytes($this->ids->get('order-1'))]
        );

        static::assertNotFalse($history);
        static::assertSame([
            'source_type' => 'sales-channel',
            'admin_user_id' => null,
            'integration_id' => null,
        ], $history);
    }

    public function testCancelRandomOrder(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/order/state/cancel',
                [
                    'orderId' => Uuid::randomHex(),
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_NOT_FOUND, $this->browser->getResponse()->getStatusCode());
        static::assertSame('CHECKOUT__ORDER_ORDER_NOT_FOUND', $response['errors'][0]['code']);
    }

    public function testCancelOtherUsersOrder(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/order/state/cancel',
                [
                    'orderId' => $this->ids->get('order-2'),
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_NOT_FOUND, $this->browser->getResponse()->getStatusCode());
        static::assertSame('CHECKOUT__ORDER_ORDER_NOT_FOUND', $response['errors'][0]['code']);
    }

    public function testCancelWithoutLogin(): void
    {
        $this->browser = $this->createCustomChannelBrowser([
            'id' => Uuid::randomHex(),
        ]);

        $this->browser
            ->request(
                'POST',
                '/store-api/order/state/cancel',
                [
                    'orderId' => $this->ids->get('order-2'),
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode());
        static::assertSame(RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE, $response['errors'][0]['code']);
    }

    private function createOrder(IdsCollection $ids, string $userId): string
    {
        $id = Uuid::randomHex();

        static::getContainer()->get('order.repository')->create(
            [[
                'id' => $id,
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'orderUser' => [
                    'userId' => $userId,
                    'email' => 'test@example.com',
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                ],
                'stateId' => $this->getStateMachineState(),
                'paymentMethodId' => $this->getValidPaymentMethodId(),
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1.0,
                'channelId' => TestDefaults::CHANNEL,
                'billingAddressId' => $billingAddressId = Uuid::randomHex(),
                'orderNumber' => Uuid::randomHex(),
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
                'lineItems' => [
                    [
                        'id' => $ids->get('VoucherA'),
                        'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                        'code' => $ids->get('VoucherA'),
                        'identifier' => $ids->get('VoucherA'),
                        'quantity' => 1,
                        'payload' => ['promotionId' => $ids->get('voucherA')],
                        'label' => 'label',
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                    [
                        'id' => $ids->get('VoucherC'),
                        'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                        'code' => $ids->get('VoucherC'),
                        'identifier' => $ids->get('VoucherC'),
                        'payload' => ['promotionId' => $ids->get('voucherB')],
                        'quantity' => 1,
                        'label' => 'label',
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                    [
                        'id' => $ids->get('VoucherB'),
                        'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                        'code' => $ids->get('VoucherB'),
                        'identifier' => $ids->get('VoucherB'),
                        'payload' => ['promotionId' => $ids->get('voucherB')],
                        'quantity' => 1,
                        'label' => 'label',
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                ],
                'deliveries' => [],
                'context' => '{}',
                'payload' => '{}',
            ]],
            Context::createDefaultContext()
        );

        return $id;
    }
}
