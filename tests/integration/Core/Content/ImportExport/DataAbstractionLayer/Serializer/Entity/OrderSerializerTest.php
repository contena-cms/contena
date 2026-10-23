<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderUser\OrderUserEntity;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\OrderSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Contena\Core\Test\Integration\Traits\OrderFixture;

/**
 * @internal
 */
class OrderSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;

    use OrderFixture;

    private OrderSerializer $serializer;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->orderRepository = static::getContainer()->get('order.repository');
        $serializerRegistry = static::getContainer()->get(SerializerRegistry::class);

        $this->serializer = new OrderSerializer();

        $this->serializer->setRegistry($serializerRegistry);

        $this->context = Context::createDefaultContext();
    }

    public function testSerializeOrder(): void
    {
        $order = $this->createOrder();
        static::assertNotNull($order->getBillingAddress());
        static::assertNotNull($order->getOrderUser());

        $orderDefinition = static::getContainer()->get('order.repository')->getDefinition();
        $config = new Config([], [], []);

        $serialized = iterator_to_array($this->serializer->serialize($config, $orderDefinition, $order));

        static::assertNotCount(0, $serialized);

        // assert values
        static::assertSame($serialized['id'], $order->getId());
        static::assertSame($serialized['orderNumber'], $order->getOrderNumber());
        static::assertSame($serialized['channelId'], $order->getChannelId());
        static::assertSame($serialized['shippingCosts'], $order->getShippingCosts()->getTotalPrice());

        static::assertInstanceOf(OrderUserEntity::class, $orderUser = $serialized['orderUser']);
        static::assertSame($orderUser->getFirstName(), $order->getOrderUser()->getFirstName());
        static::assertSame($orderUser->getLastName(), $order->getOrderUser()->getLastName());
        static::assertSame($orderUser->getEmail(), $order->getOrderUser()->getEmail());

        static::assertInstanceOf(OrderAddressEntity::class, $billingAddress = $serialized['billingAddress']);
        static::assertSame($billingAddress->getZipcode(), $order->getBillingAddress()->getZipcode());
        static::assertSame($billingAddress->getStreet(), $order->getBillingAddress()->getStreet());
        static::assertSame($billingAddress->getCity(), $order->getBillingAddress()->getCity());
        static::assertSame($billingAddress->getCompany(), $order->getBillingAddress()->getCompany());
        static::assertSame($billingAddress->getDepartment(), $order->getBillingAddress()->getDepartment());
        static::assertSame($billingAddress->getCountryId(), $order->getBillingAddress()->getCountryId());
        static::assertSame($billingAddress->getCountryStateId(), $order->getBillingAddress()->getCountryStateId());

        static::assertNotNull($deliveries = $order->getDeliveries());
        static::assertNotNull($delivery = $deliveries->first());

        static::assertNotCount(0, $serialized['deliveries']);
        static::assertSame($serialized['deliveries']['trackingCodes'], implode('|', $delivery->getTrackingCodes()));
        static::assertSame($serialized['deliveries']['shippingOrderAddress'], $delivery->getShippingOrderAddress());
        static::assertSame($serialized['deliveries']['stateMachineState'], $delivery->getStateMachineState());
        static::assertSame($serialized['deliveries']['shippingCosts'], $delivery->getShippingCosts()->getTotalPrice());

        static::assertNotNull($transactions = $order->getTransactions());
        static::assertNotNull($transaction = $transactions->first());

        static::assertSame($serialized['transactions']['_uniqueIdentifier'], $transaction->getUniqueIdentifier());
        static::assertSame($serialized['transactions']['id'], $transaction->getId());
        static::assertSame($serialized['transactions']['versionId'], $transaction->getVersionId());
        static::assertSame($serialized['transactions']['orderId'], $transaction->getOrderId());
        static::assertSame($serialized['transactions']['orderVersionId'], $transaction->getOrderVersionId());
        static::assertSame($serialized['transactions']['paymentMethodId'], $transaction->getPaymentMethodId());
        static::assertSame($serialized['transactions']['amount'], $transaction->getAmount()->getTotalPrice());
        static::assertSame($serialized['transactions']['stateId'], $transaction->getStateId());
        static::assertSame($serialized['transactions']['stateMachineState'], $transaction->getStateMachineState()?->jsonSerialize());

        static::assertNotNull($lineItems = $order->getLineItems());
        static::assertNotNull($lineItem = $lineItems->first());

        static::assertSame($serialized['lineItems'], '1x ' . $lineItem->getBlogId());

        static::assertSame($serialized['amountTotal'], $order->getAmountTotal());
        static::assertSame($serialized['stateId'], $order->getStateId());
        static::assertSame($serialized['orderDateTime'], $order->getOrderDateTime()->format('Y-m-d\Th:i:s.vP'));

        static::assertNotNull($itemRounding = $order->getItemRounding());
        static::assertSame($serialized['itemRounding'], $itemRounding->jsonSerialize());

        static::assertNotNull($totalRounding = $order->getTotalRounding());
        static::assertSame($serialized['totalRounding'], $totalRounding->jsonSerialize());
    }

    private function createOrder(): OrderEntity
    {
        // create blog
        $blogId = Uuid::randomHex();
        $blog = $this->getBlogData($blogId);

        $blogRepository = static::getContainer()->get('blog.repository');
        $blogRepository->create([$blog], $this->context);

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $this->context)[0];

        $orderData['lineItems'][0]['blogId'] = $blogId;

        $orderData['transactions'] = [
            $this->getTransactionData($orderData),
        ];

        $this->orderRepository->create([$orderData], $this->context);

        $criteria = new Criteria();

        $criteria->addAssociations([
            'lineItems',
            'billingAddress',
            'deliveries.stateMachineState',
            'deliveries.shippingOrderAddress',
            'deliveries.stateMachineState',
            'transactions.stateMachineState',
            'transactions.shippingMethod',
        ]);

        $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->first();

        static::assertInstanceOf(OrderEntity::class, $order);

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function getBlogData(string $blogId): array
    {
        return [
            'id' => $blogId,
            'stock' => 101,
            'blogNumber' => 'P101',
            'active' => true,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'test blog',
                ],
            ],
            'tax' => [
                'name' => '19%',
                'taxRate' => 19.0,
            ],
            'price' => [
                Defaults::CURRENCY => [
                    'gross' => 1.111,
                    'net' => 1.011,
                    'linked' => true,
                    'currencyId' => Defaults::CURRENCY,
                    'listPrice' => [
                        'gross' => 1.111,
                        'net' => 1.011,
                        'linked' => false,
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $orderData
     *
     * @return array<string, mixed>
     */
    private function getTransactionData(array $orderData): array
    {
        $paymentMethod = static::getContainer()->get('payment_method.repository')->search(new Criteria(), $this->context)->getEntities()->first();
        $paymentMethodId = null;
        if ($paymentMethod instanceof PaymentMethodEntity) {
            $paymentMethodId = $paymentMethod->getId();
        }

        $stateMachineState = static::getContainer()->get('state_machine_state.repository')->search(new Criteria(), $this->context)->getEntities()->first();
        $stateMachineStateId = null;
        if ($stateMachineState instanceof StateMachineStateEntity) {
            $stateMachineStateId = $stateMachineState->getId();
        }

        return [
            'id' => Uuid::randomHex(),
            'orderId' => $orderData['id'],
            'orderVersionId' => $orderData['versionId'],
            'paymentMethodId' => $paymentMethodId,
            'amount' => [
                'quantity' => 1,
                'taxRules' => [],
                'listPrice' => null,
                'unitPrice' => 20.02,
                'totalPrice' => 20.02,
                'referencePrice' => null,
                'calculatedTaxes' => [],
                'regulationPrice' => null,
            ],
            'stateId' => $stateMachineStateId,
        ];
    }
}
