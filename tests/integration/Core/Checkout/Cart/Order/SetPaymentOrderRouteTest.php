<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Order;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Order\Channel\SetPaymentOrderRoute;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Integration\Traits\OrderFixture;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class SetPaymentOrderRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use OrderFixture;

    private SetPaymentOrderRoute $setPaymentOrderRoute;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setPaymentOrderRoute = static::getContainer()->get(SetPaymentOrderRoute::class);

        $this->userRepository = static::getContainer()->get('user.repository');

        $this->orderRepository = static::getContainer()->get('order.repository');
    }

    public function testSetPaymentUpdatePrimary(): void
    {
        $user = $this->createUser();
        static::assertNotNull($user);

        $orderId = Uuid::randomHex();
        $context = Generator::generateChannelContext(user: $user);

        $this->createOrder($orderId, $user->getId());

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->request->set('paymentMethodId', $this->getValidPaymentMethodId());
        $request->request->set('orderId', $orderId);
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $this->setPaymentOrderRoute->setPayment($request, $context);

        $order = $this->orderRepository->search(new Criteria(), $context->getContext())->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);
        static::assertNotNull($order->getPrimaryOrderTransactionId());
    }

    public function testCorrectTransactionAmount(): void
    {
        $user = $this->createUser();
        static::assertNotNull($user);

        $orderId = Uuid::randomHex();
        $context = Generator::generateChannelContext(user: $user);
        $transactionId = Uuid::randomHex();
        $validPaymentId = $this->getValidPaymentMethodId();

        $override = [
            'primaryOrderTransactionId' => $transactionId,
            'transactions' => [
                [
                    'id' => $transactionId,
                    'paymentMethodId' => $validPaymentId,
                    'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderTransactionStates::STATE_MACHINE),
                    'amount' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                ],
            ],
        ];
        $this->createOrder($orderId, $user->getId(), $override);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->request->set('paymentMethodId', $validPaymentId);
        $request->request->set('orderId', $orderId);
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $this->setPaymentOrderRoute->setPayment($request, $context);

        $criteria = new Criteria();
        $criteria->addAssociations(['primaryOrderTransaction', 'transactions']);

        $order = $this->orderRepository->search($criteria, $context->getContext())->getEntities()->first();

        static::assertInstanceOf(OrderEntity::class, $order);
        static::assertNotNull($order->getPrimaryOrderTransactionId());
        static::assertNotNull($order->getTransactions());
        static::assertNotNull($order->getPrimaryOrderTransaction());

        static::assertSame($transactionId, $order->getPrimaryOrderTransactionId());
        static::assertCount(1, $order->getTransactions());
        static::assertSame(10.0, $order->getPrimaryOrderTransaction()->getAmount()->getTotalPrice());
        static::assertSame(10.0, $order->getPrimaryOrderTransaction()->getAmount()->getUnitPrice());
    }

    public function testInconsistentTransactionAmount(): void
    {
        $user = $this->createUser();
        static::assertNotNull($user);

        $orderId = Uuid::randomHex();
        $context = Generator::generateChannelContext(user: $user);
        $transactionId = Uuid::randomHex();
        $validPaymentId = $this->getValidPaymentMethodId();

        // Simulate an outdated transaction. E.g., line item price has changed via admin
        $override = [
            'primaryOrderTransactionId' => $transactionId,
            'transactions' => [
                [
                    'id' => $transactionId,
                    'paymentMethodId' => $validPaymentId,
                    'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderTransactionStates::STATE_MACHINE),
                    'amount' => new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection()),
                ],
            ],
        ];
        $this->createOrder($orderId, $user->getId(), $override);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->request->set('paymentMethodId', $validPaymentId);
        $request->request->set('orderId', $orderId);
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $this->setPaymentOrderRoute->setPayment($request, $context);

        $criteria = new Criteria();
        $criteria->addAssociations(['primaryOrderTransaction', 'transactions']);

        $order = $this->orderRepository->search($criteria, $context->getContext())->getEntities()->first();

        static::assertInstanceOf(OrderEntity::class, $order);
        static::assertNotNull($order->getPrimaryOrderTransactionId());
        static::assertNotNull($order->getTransactions());
        static::assertNotNull($order->getPrimaryOrderTransaction());

        static::assertNotSame($transactionId, $order->getPrimaryOrderTransactionId());
        static::assertCount(2, $order->getTransactions());
        static::assertSame(10.0, $order->getPrimaryOrderTransaction()->getAmount()->getTotalPrice());
        static::assertSame(10.0, $order->getPrimaryOrderTransaction()->getAmount()->getUnitPrice());
    }

    private function createUser(): ?UserEntity
    {
        $id1 = Uuid::randomHex();

        $salutationId = $this->getValidSalutationId();

        $this->userRepository->create([[
            'id' => $id1,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $id1,
                'firstName' => 'not',
                'lastName' => 'not',
                'city' => 'not',
                'street' => 'not',
                'zipcode' => 'not',
                'salutationId' => $salutationId,
                'country' => ['name' => 'not'],
            ],
            'defaultBillingAddressId' => $id1,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'not12345',
            'lastName' => 'not',
            'firstName' => 'First name',
            'salutationId' => $salutationId,
            'userNumber' => 'not',
        ]], Context::createDefaultContext());

        return $this->userRepository->search(new Criteria([$id1]), Context::createDefaultContext())->getEntities()->first();
    }

    /**
     * @param array<string, mixed> $override
     */
    private function createOrder(string $id, string $userId, array $override = []): void
    {
        $orderData = $this->getOrderData($id, Context::createDefaultContext())[0];
        $orderData['orderUser']['user']['id'] = $userId;

        $this->orderRepository->create([array_merge($orderData, $override)], Context::createDefaultContext());
    }
}
