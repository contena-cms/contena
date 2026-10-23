<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Order\Channel;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Contena\Core\Checkout\Order\Event\OrderPaymentMethodChangedCriteriaEvent;
use Contena\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class SetPaymentOrderRouteTest extends TestCase
{
    use UserTestTrait;
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private ?OrderPaymentMethodChangedEvent $paymentMethodChangedEventResult;

    private ?OrderPaymentMethodChangedCriteriaEvent $paymentMethodChangedCriteriaEventResult;

    private ?StateMachineTransitionEvent $transactionStateEventResult;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'paymentMethods' => [
                ['id' => $this->getAvailablePaymentMethodId()],
                ['id' => $this->getAvailablePaymentMethodId(1)],
            ],
        ]);

        $this->assignChannelContext($this->browser);

        $email = Uuid::randomHex() . '@example.com';
        $userId = $this->createUser($email);

        $this->ids->set('order-1', $this->createOrder($userId));
        $this->ids->set('order-2', $this->createOrder($this->createUser('test-other@test.de')));

        $this->browser->request(
            'POST',
            '/store-api/account/login',
            [
                'email' => $email,
                'password' => 'contena',
            ]
        );
        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->paymentMethodChangedEventResult = null;
        $this->catchEvent(OrderPaymentMethodChangedEvent::class, $this->paymentMethodChangedEventResult);

        $this->paymentMethodChangedCriteriaEventResult = null;
        $this->catchEvent(OrderPaymentMethodChangedCriteriaEvent::class, $this->paymentMethodChangedCriteriaEventResult);

        $this->transactionStateEventResult = null;
        $this->catchEvent(StateMachineTransitionEvent::class, $this->transactionStateEventResult);
    }

    public function testSetPaymentMethodOwnOrderOtherPaymentMethodOpen(): void
    {
        $this->sendValidRequest($this->ids->get('order-1'), $this->getAvailablePaymentMethodId(1));
        $transactions = $this->getTransactions($this->ids->get('order-1'));
        static::assertCount(2, $transactions);
        $firstTransaction = $transactions->first();
        static::assertNotNull($firstTransaction);
        $lastTransaction = $transactions->last();
        static::assertNotNull($lastTransaction);
        static::assertNotSame($firstTransaction->getId(), $lastTransaction->getId());

        static::assertNotNull($firstTransaction->getStateMachineState());
        static::assertNotNull($lastTransaction->getStateMachineState());
        static::assertSame('cancelled', $firstTransaction->getStateMachineState()->getTechnicalName());
        static::assertSame('open', $lastTransaction->getStateMachineState()->getTechnicalName());

        static::assertNotNull($this->paymentMethodChangedCriteriaEventResult);
        static::assertNotNull($this->paymentMethodChangedEventResult);
        static::assertSame($lastTransaction->getId(), $this->paymentMethodChangedEventResult->getOrderTransaction()->getId());
        $result = $this->transactionStateEventResult;
        static::assertNotNull($result);
        static::assertSame($firstTransaction->getId(), $result->getEntityId());
        static::assertSame('open', $result->getFromPlace()->getTechnicalName());
        static::assertSame('cancelled', $result->getToPlace()->getTechnicalName());
    }

    public function testSetPaymentMethodOwnOrderOtherPaymentMethodCancelled(): void
    {
        $this->setFirstTransactionState($this->ids->get('order-1'));
        $this->sendValidRequest($this->ids->get('order-1'), $this->getAvailablePaymentMethodId(1));
        $transactions = $this->getTransactions($this->ids->get('order-1'));
        static::assertCount(2, $transactions);
        $firstTransaction = $transactions->first();
        static::assertNotNull($firstTransaction);
        $lastTransaction = $transactions->last();
        static::assertNotNull($lastTransaction);
        static::assertNotSame($firstTransaction->getId(), $lastTransaction->getId());

        static::assertNotNull($firstTransaction->getStateMachineState());
        static::assertNotNull($lastTransaction->getStateMachineState());
        static::assertSame('cancelled', $firstTransaction->getStateMachineState()->getTechnicalName());
        static::assertSame('open', $lastTransaction->getStateMachineState()->getTechnicalName());

        static::assertNotNull($this->paymentMethodChangedCriteriaEventResult);
        static::assertNotNull($this->paymentMethodChangedEventResult);
        static::assertSame($lastTransaction->getId(), $this->paymentMethodChangedEventResult->getOrderTransaction()->getId());
        static::assertNull($this->transactionStateEventResult);
    }

    public function testSetPaymentMethodOwnOrderWithSamePaymentMethodOpen(): void
    {
        $this->sendValidRequest($this->ids->get('order-1'), $this->getAvailablePaymentMethodId());

        $transactions = $this->getTransactions($this->ids->get('order-1'));
        static::assertCount(1, $transactions);
        $lastTransaction = $transactions->last();
        static::assertNotNull($lastTransaction);

        static::assertNotNull($lastTransaction->getStateMachineState());
        static::assertSame('open', $lastTransaction->getStateMachineState()->getTechnicalName());
        static::assertNotNull($this->paymentMethodChangedCriteriaEventResult);
        static::assertNull($this->paymentMethodChangedEventResult);
        static::assertNull($this->transactionStateEventResult);
    }

    public function testSetPaymentMethodOwnOrderWithSamePaymentMethodCancelled(): void
    {
        $this->setFirstTransactionState($this->ids->get('order-1'));
        $this->sendValidRequest($this->ids->get('order-1'), $this->getAvailablePaymentMethodId());

        $transactions = $this->getTransactions($this->ids->get('order-1'));
        static::assertCount(1, $transactions);
        $lastTransaction = $transactions->last();
        static::assertNotNull($lastTransaction);

        static::assertNotNull($lastTransaction->getStateMachineState());
        static::assertSame('open', $lastTransaction->getStateMachineState()->getTechnicalName());
        static::assertNotNull($this->paymentMethodChangedCriteriaEventResult);
        static::assertNull($this->paymentMethodChangedEventResult);
        $result = $this->transactionStateEventResult;
        static::assertNotNull($result);
        static::assertSame($lastTransaction->getId(), $result->getEntityId());
        static::assertSame('cancelled', $result->getFromPlace()->getTechnicalName());
        static::assertSame('open', $result->getToPlace()->getTechnicalName());
    }

    public function testSetPaymentMethodOwnOrderWithSamePaymentMethodChangedAmount(): void
    {
        $orderId = $this->ids->get('order-1');
        $this->setOrderAmount($orderId, 20.0);

        $this->sendValidRequest($orderId, $this->getAvailablePaymentMethodId());

        $transactions = $this->getTransactions($orderId);
        static::assertCount(2, $transactions);
        $firstTransaction = $transactions->first();
        static::assertNotNull($firstTransaction);
        $lastTransaction = $transactions->last();
        static::assertNotNull($lastTransaction);
        static::assertNotSame($firstTransaction->getId(), $lastTransaction->getId());

        static::assertNotNull($firstTransaction->getStateMachineState());
        static::assertNotNull($lastTransaction->getStateMachineState());
        static::assertSame('cancelled', $firstTransaction->getStateMachineState()->getTechnicalName());
        static::assertSame('open', $lastTransaction->getStateMachineState()->getTechnicalName());

        static::assertNotNull($this->paymentMethodChangedCriteriaEventResult);
        static::assertNotNull($this->paymentMethodChangedEventResult);
        static::assertSame($lastTransaction->getId(), $this->paymentMethodChangedEventResult->getOrderTransaction()->getId());
        $result = $this->transactionStateEventResult;
        static::assertNotNull($result);
        static::assertSame($firstTransaction->getId(), $result->getEntityId());
        static::assertSame('open', $result->getFromPlace()->getTechnicalName());
        static::assertSame('cancelled', $result->getToPlace()->getTechnicalName());
    }

    public function testSetPaymentMethodOwnOrderWithSamePaymentMethodInNotMostRecentTransaction(): void
    {
        $this->sendValidRequest($this->ids->get('order-1'), $this->getAvailablePaymentMethodId(1));
        $this->setFirstTransactionState($this->ids->get('order-1'), OrderTransactionStates::STATE_OPEN);
        $this->sendValidRequest($this->ids->get('order-1'), $this->getAvailablePaymentMethodId());

        $transactions = $this->getTransactions($this->ids->get('order-1'));
        static::assertCount(3, $transactions);
        $firstTransaction = $transactions->first();
        static::assertNotNull($firstTransaction);
        $lastTransaction = $transactions->last();
        static::assertNotNull($lastTransaction);

        $paymentMethodChangedCriteriaEventResult = $this->paymentMethodChangedCriteriaEventResult;
        $paymentMethodChangedEventResult = $this->paymentMethodChangedEventResult;
        $transactionStateEventResult = $this->transactionStateEventResult;
        static::assertNotNull($paymentMethodChangedEventResult);
        static::assertNotNull($transactionStateEventResult);
        static::assertNotNull($paymentMethodChangedCriteriaEventResult);
        static::assertNotNull($lastTransaction->getStateMachineState());
        static::assertSame('open', $lastTransaction->getStateMachineState()->getTechnicalName());
        static::assertSame($lastTransaction->getId(), $paymentMethodChangedEventResult->getOrderTransaction()->getId());
        static::assertNotSame($firstTransaction->getId(), $transactionStateEventResult->getEntityId());
        static::assertNotSame($lastTransaction->getId(), $transactionStateEventResult->getEntityId());
        static::assertSame('open', $transactionStateEventResult->getFromPlace()->getTechnicalName());
        static::assertSame('cancelled', $transactionStateEventResult->getToPlace()->getTechnicalName());
    }

    public function testSetPaymentMethodRandomOrder(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [
                    'orderId' => Uuid::randomHex(),
                    'paymentMethodId' => $this->getAvailablePaymentMethodId(1),
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_NOT_FOUND, $this->browser->getResponse()->getStatusCode());
        static::assertSame('CHECKOUT__ORDER_ORDER_NOT_FOUND', $response['errors'][0]['code']);
        static::assertNotNull($this->paymentMethodChangedCriteriaEventResult);
        static::assertNull($this->paymentMethodChangedEventResult);
        static::assertNull($this->transactionStateEventResult);
    }

    public function testSetPaymentMethodOtherUsersOrder(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [
                    'orderId' => $this->ids->get('order-2'),
                    'paymentMethodId' => $this->getAvailablePaymentMethodId(1),
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_NOT_FOUND, $this->browser->getResponse()->getStatusCode());
        static::assertSame('CHECKOUT__ORDER_ORDER_NOT_FOUND', $response['errors'][0]['code']);
        static::assertNotNull($this->paymentMethodChangedCriteriaEventResult);
        static::assertNull($this->paymentMethodChangedEventResult);
        static::assertNull($this->transactionStateEventResult);
    }

    public function testSetPaymentMethodWithoutLogin(): void
    {
        $this->browser = $this->createCustomChannelBrowser([
            'id' => Uuid::randomHex(),
        ]);

        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [
                    'orderId' => $this->ids->get('order-2'),
                    'paymentMethodId' => $this->getAvailablePaymentMethodId(1),
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode());
        static::assertSame(RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE, $response['errors'][0]['code']);
        static::assertNull($this->paymentMethodChangedCriteriaEventResult);
        static::assertNull($this->paymentMethodChangedEventResult);
        static::assertNull($this->transactionStateEventResult);
    }

    public function testSetPaymentMethodValidatePaymentStateValidChange(): void
    {
        $this->sendValidRequest($this->ids->get('order-1'), $this->getAvailablePaymentMethodId(1));
        $transactions = $this->getTransactions($this->ids->get('order-1'));
        static::assertCount(2, $transactions);
        $firstTransaction = $transactions->first();
        static::assertNotNull($firstTransaction);
        $lastTransaction = $transactions->last();
        static::assertNotNull($lastTransaction);
        static::assertNotSame($firstTransaction->getId(), $lastTransaction->getId());

        static::assertNotNull($firstTransaction->getStateMachineState());
        static::assertNotNull($lastTransaction->getStateMachineState());
        static::assertSame('cancelled', $firstTransaction->getStateMachineState()->getTechnicalName());
        static::assertSame('open', $lastTransaction->getStateMachineState()->getTechnicalName());

        static::assertNotNull($this->paymentMethodChangedCriteriaEventResult);
        static::assertNotNull($this->paymentMethodChangedEventResult);
        static::assertSame($lastTransaction->getId(), $this->paymentMethodChangedEventResult->getOrderTransaction()->getId());
        $result = $this->transactionStateEventResult;
        static::assertNotNull($result);
        static::assertSame($firstTransaction->getId(), $result->getEntityId());
        static::assertSame('open', $result->getFromPlace()->getTechnicalName());
        static::assertSame('cancelled', $result->getToPlace()->getTechnicalName());
    }

    public function testSetPaymentMethodValidatePaymentStateInvalidChange(): void
    {
        $orderId = $this->ids->get('order-1');
        $this->setFirstTransactionState($orderId, OrderTransactionStates::STATE_AUTHORIZED);

        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [
                    'orderId' => $orderId,
                    'paymentMethodId' => $this->getAvailablePaymentMethodId(1),
                ]
            );

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode());
    }

    public function testSetPaymentMethodNotAfterOrderEnabled(): void
    {
        $orderId = $this->ids->get('order-1');
        $paymentMethodId = $this->getAvailablePaymentMethodId(1);

        // Disable "Allow payment change after checkout" for the otherwise available target method.
        static::getContainer()->get('payment_method.repository')->update([[
            'id' => $paymentMethodId,
            'afterOrderEnabled' => false,
        ]], Context::createDefaultContext());

        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [
                    'orderId' => $orderId,
                    'paymentMethodId' => $paymentMethodId,
                ]
            );

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode());

        // The order must not have gained a transaction for the disallowed method.
        static::assertCount(1, $this->getTransactions($orderId));
    }

    private function createOrder(string $userId): string
    {
        $id = Uuid::randomHex();
        $transactionId = Uuid::randomHex();

        static::getContainer()->get('order.repository')->create(
            [[
                'id' => $id,
                'primaryOrderTransactionId' => $transactionId,
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
                'orderNumber' => 'anOrderNumber',
                'stateId' => $this->getStateMachineState(),
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1.0,
                'channelId' => TestDefaults::CHANNEL,
                'billingAddressId' => $billingAddressId = Uuid::randomHex(),
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
                        'id' => $this->ids->get('VoucherA'),
                        'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                        'code' => $this->ids->get('VoucherA'),
                        'identifier' => $this->ids->get('VoucherA'),
                        'quantity' => 1,
                        'payload' => ['promotionId' => $this->ids->get('voucherA')],
                        'label' => 'label',
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                ],
                'deliveries' => [],
                'transactions' => [
                    [
                        'id' => $transactionId,
                        'paymentMethodId' => $this->getAvailablePaymentMethodId(),
                        'stateId' => $this->getStateMachineState(OrderTransactionStates::STATE_MACHINE, OrderTransactionStates::STATE_OPEN),
                        'amount' => new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    ],
                ],
                'context' => '{}',
                'payload' => '{}',
            ]],
            Context::createDefaultContext()
        );

        return $id;
    }

    private function sendValidRequest(string $orderId, string $paymentMethodId): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [
                    'orderId' => $orderId,
                    'paymentMethodId' => $paymentMethodId,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);
    }

    private function getAvailablePaymentMethodId(int $offset = 0): string
    {
        /** @var EntityRepository<PaymentMethodCollection> $repository */
        $repository = static::getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->setOffset($offset)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('availabilityRuleId', null));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNotNull($id);

        return $id;
    }

    private function getTransactions(string $orderId): OrderTransactionCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addSorting(new FieldSorting('createdAt'));

        $criteria->addAssociation('stateMachineState');

        /** @var OrderTransactionCollection $transactions */
        $transactions = static::getContainer()->get('order_transaction.repository')->search($criteria, Context::createDefaultContext())->getEntities();

        return $transactions;
    }

    private function setFirstTransactionState(string $orderId, string $state = OrderTransactionStates::STATE_CANCELLED): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addSorting(new FieldSorting('createdAt'));

        $transactionId = static::getContainer()->get('order_transaction.repository')->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::getContainer()->get('order_transaction.repository')->update([[
            'id' => $transactionId,
            'stateId' => $this->getStateMachineState(OrderTransactionStates::STATE_MACHINE, $state),
        ]], Context::createDefaultContext());
    }

    private function setOrderAmount(string $orderId, float $amount): void
    {
        static::getContainer()->get('order.repository')->update([[
            'id' => $orderId,
            'price' => new CartPrice($amount, $amount, $amount, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
        ]], Context::createDefaultContext());
    }
}
